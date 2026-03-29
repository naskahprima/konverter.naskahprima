<?php

namespace App\Jobs;

use App\Models\Conversion;
use App\Models\ConversionMessage;
use App\Services\DocxService;
use App\Services\GeminiService;
use App\Services\UrlReaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * ProcessConversionJob
 *
 * Orkestrator utama konversi skripsi → jurnal.
 * Berjalan di background (queue), bukan request-response langsung.
 *
 * Phase:
 *   analyze → PHASE 2 & 3: Baca semua dokumen + generate diagnosis
 *   convert → PHASE 5: Generate konten jurnal + buat file DOCX
 */
class ProcessConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 menit max per job
    public int $tries   = 2;   // retry sekali kalau gagal

    public function __construct(
        private readonly int    $conversionId,
        private readonly string $phase = 'analyze' // 'analyze' | 'convert'
    ) {}

    public function handle(
        GeminiService    $gemini,
        UrlReaderService $urlReader,
        DocxService      $docx
    ): void {
        $conversion = Conversion::with('files', 'messages')->find($this->conversionId);

        if (!$conversion) {
            Log::error("ProcessConversionJob: Conversion {$this->conversionId} tidak ditemukan.");
            return;
        }

        try {
            match ($this->phase) {
                'analyze' => $this->runAnalysisPhase($conversion, $gemini, $urlReader),
                'convert' => $this->runConversionPhase($conversion, $gemini, $docx),
                default   => Log::error("ProcessConversionJob: Phase tidak dikenal — {$this->phase}"),
            };
        } catch (\Exception $e) {
            Log::error("ProcessConversionJob exception [{$this->phase}]: " . $e->getMessage());
            $this->failConversion($conversion, 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PHASE: ANALYZE
    // ═══════════════════════════════════════════════════════════════════════════

    private function runAnalysisPhase(
        Conversion       $conversion,
        GeminiService    $gemini,
        UrlReaderService $urlReader
    ): void {
        $this->msg($conversion, 'system', 'info', '🔍 Proses analisis dimulai...');
        $conversion->update(['status' => Conversion::STATUS_ANALYZING]);

        // ── STEP 1: Baca & ekstrak skripsi ───────────────────────────────────
        $this->msg($conversion, 'ai', 'info', '📄 Membaca file skripsi...');

        $thesisContent = $this->extractSkripsi($conversion);
        if (!$thesisContent) {
            $this->failConversion($conversion, 'Gagal membaca file skripsi. Pastikan file tidak rusak atau terproteksi password.');
            return;
        }

        $conversion->update(['thesis_content' => mb_substr($thesisContent, 0, 100000)]);
        $this->msg($conversion, 'ai', 'info', '✅ Skripsi berhasil dibaca!');

        // ── STEP 2: Baca Author Guide ─────────────────────────────────────────
        $authorGuideContent = $this->readAuthorGuide($conversion, $urlReader);
        if ($authorGuideContent === null) {
            // runAnalysisPhase sudah handle fallback message di dalam readAuthorGuide
            return;
        }

        $conversion->update(['author_guide_content' => mb_substr($authorGuideContent, 0, 50000)]);
        $this->msg($conversion, 'ai', 'info', '✅ Author Guide berhasil dibaca!');

        // ── STEP 3: Baca Archive Jurnal ───────────────────────────────────────
        $archiveContent = $this->readArchive($conversion, $urlReader);
        // Archive bisa kosong kalau semua URL gagal — kita lanjut dengan data terbatas
        $conversion->update(['archive_content' => mb_substr($archiveContent, 0, 80000)]);

        // ── STEP 4: AI Analisis Dokumen ───────────────────────────────────────
        $this->msg($conversion, 'ai', 'info', '🧠 AI sedang menganalisis Author Guide...');
        $authorGuideAnalysis = $gemini->analyzeAuthorGuide($authorGuideContent);
        if (!$authorGuideAnalysis) {
            $this->failConversion($conversion, 'Gagal menganalisis Author Guide. Coba lagi.');
            return;
        }

        $this->msg($conversion, 'ai', 'info', '🧠 AI sedang menganalisis pola archive jurnal...');
        $archiveAnalysis = null;
        if (!empty(trim($archiveContent))) {
            $archiveAnalysis = $gemini->analyzeArchivePatterns($archiveContent);
        }
        $archiveAnalysis ??= ['note' => 'Data archive tidak tersedia, AI menggunakan pola dari Author Guide'];

        $this->msg($conversion, 'ai', 'info', '🧠 AI sedang menganalisis skripsi...');
        $thesisSummary = $gemini->extractThesisSummary($thesisContent);
        if (!$thesisSummary) {
            $this->failConversion($conversion, 'Gagal menganalisis skripsi. Coba lagi.');
            return;
        }

        // ── STEP 5: Generate Diagnosis Report ────────────────────────────────
        $this->msg($conversion, 'ai', 'info', '📊 AI sedang membuat diagnosis report...');
        $diagnosis = $gemini->generateDiagnosisReport($authorGuideAnalysis, $archiveAnalysis, $thesisSummary);
        if (!$diagnosis) {
            $this->failConversion($conversion, 'Gagal membuat diagnosis. Coba lagi.');
            return;
        }

        // Simpan hasil analisis
        $conversion->update([
            'scope_match'           => $diagnosis['scope_match'] ?? 'unknown',
            'scope_match_reason'    => $diagnosis['scope_match_reason'] ?? '',
            'title_recommendations' => $diagnosis['title_recommendations'] ?? [],
            'gap_analysis'          => json_encode($diagnosis['gap_analysis'] ?? [], JSON_UNESCAPED_UNICODE),
            'compliance_check'      => $diagnosis['compliance_check'] ?? [],
            'diagnosis_report'      => json_encode($diagnosis, JSON_UNESCAPED_UNICODE),
            'qa_questions'          => $diagnosis['smart_questions'] ?? [],
            'status'                => Conversion::STATUS_AWAITING_QA,
        ]);

        // Tampilkan diagnosis ke user sebagai pesan
        $diagnosisMessage = $this->buildDiagnosisMessage($diagnosis);
        $this->msg($conversion, 'ai', 'diagnosis', $diagnosisMessage);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PHASE: CONVERT
    // ═══════════════════════════════════════════════════════════════════════════

    private function runConversionPhase(
        Conversion    $conversion,
        GeminiService $gemini,
        DocxService   $docx
    ): void {
        $this->msg($conversion, 'system', 'info', '⚙️ Memulai proses konversi...');
        $conversion->update(['status' => Conversion::STATUS_CONVERTING]);

        $diagnosis  = json_decode($conversion->diagnosis_report ?? '[]', true) ?? [];
        $qaAnswers  = $conversion->qa_answers ?? [];

        // Judul yang dipilih user (kalau tidak dipilih, pakai rekomendasi pertama)
        $selectedTitle = $qaAnswers['selected_title']
            ?? ($conversion->title_recommendations[0]['title'] ?? 'Judul Jurnal');

        $authorGuideAnalysis = json_decode(
            json_encode($diagnosis['compliance_check'] ?? []),
            true
        ) ?? [];

        // Coba parse author guide analysis dari content
        // (disimpan sebagai teks, bukan JSON)
        $agContent = $conversion->author_guide_content ?? '';

        $this->msg($conversion, 'ai', 'info', '✍️ AI sedang menulis konten jurnal...');

        $journalContent = $gemini->generateJournalContent(
            thesisContent:       $conversion->thesis_content ?? '',
            authorGuideAnalysis: $authorGuideAnalysis,
            selectedTitle:       $selectedTitle,
            diagnosisReport:     $diagnosis,
            qaAnswers:           $qaAnswers
        );

        if (!$journalContent) {
            $this->failConversion($conversion, 'Gagal generate konten jurnal. Coba lagi.');
            return;
        }

        // Generate DOCX
        $this->msg($conversion, 'ai', 'info', '📝 Membuat file Word...');

        $templateFile = $conversion->files()->where('type', 'template')->first();
        $outputPath   = $docx->generate($templateFile?->path, $journalContent);

        $conversion->update([
            'status'              => Conversion::STATUS_COMPLETED,
            'output_path'         => $outputPath,
            'submission_checklist' => json_encode(
                $journalContent['submission_checklist'] ?? [],
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        $this->msg(
            $conversion,
            'ai',
            'success',
            "🎉 **Konversi selesai!** File jurnal kamu sudah siap didownload.\n\n"
            . "Jangan lupa baca **Submission Checklist** di halaman berikutnya sebelum submit ke jurnal ya!"
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS — Baca Dokumen
    // ═══════════════════════════════════════════════════════════════════════════

    private function extractSkripsi(Conversion $conversion): ?string
    {
        $file = $conversion->files()->where('type', 'skripsi')->first();
        return $file ? $this->extractFileContent($file->fullPath(), $file->extension()) : null;
    }

    private function readAuthorGuide(Conversion $conversion, UrlReaderService $urlReader): ?string
    {
        // Coba dari URL dulu
        if ($conversion->author_guide_url) {
            $content = $urlReader->read($conversion->author_guide_url);
            if ($content && !str_starts_with($content, '__PDF_URL__:')) {
                return $content;
            }
        }

        // Coba dari file manual yang sudah diupload
        $manualFile = $conversion->files()->where('type', 'author_guide_manual')->first();
        if ($manualFile) {
            $this->msg($conversion, 'ai', 'info', '📁 Membaca Author Guide dari file yang diupload...');
            return $this->extractFileContent($manualFile->fullPath(), $manualFile->extension());
        }

        // ⚠️ FALLBACK — Minta user upload manual
        $conversion->update([
            'status'               => Conversion::STATUS_WAITING_FALLBACK,
            'author_guide_fallback' => true,
        ]);

        $this->msg(
            $conversion,
            'ai',
            'fallback_request',
            "⚠️ **Maaf, website Author Guide tidak bisa diakses secara otomatis.**\n\n"
            . "Ini bisa terjadi karena website jurnal memiliki perlindungan bot, atau sedang down.\n\n"
            . "**Yang perlu kamu lakukan:**\n"
            . "1. Buka website jurnal target secara manual\n"
            . "2. Download halaman Author Guide / Submission Guidelines (biasanya bisa Save as PDF)\n"
            . "3. Upload file PDF atau Word tersebut di form di bawah\n\n"
            . "Setelah upload, klik **Lanjutkan Analisis** dan AI akan melanjutkan prosesnya."
        );

        return null; // Signal ke caller untuk berhenti
    }

    private function readArchive(Conversion $conversion, UrlReaderService $urlReader): string
    {
        $archiveContent = '';
        $archiveUrls    = $conversion->archive_urls ?? [];

        if (!empty($archiveUrls)) {
            $this->msg($conversion, 'ai', 'info', '🔗 Membaca archive jurnal yang lolos...');
            $results     = $urlReader->readMultiple($archiveUrls);
            $successCount = $urlReader->countSuccess($results);
            $archiveContent = $urlReader->mergeContent($results);

            if ($successCount === 0) {
                $conversion->update(['archive_fallback' => true]);

                $this->msg(
                    $conversion,
                    'ai',
                    'fallback_request',
                    "⚠️ **Archive jurnal tidak bisa diakses otomatis dari URL yang diberikan.**\n\n"
                    . "Kamu bisa upload contoh jurnal yang sudah lolos secara manual (3-5 file PDF) "
                    . "di form yang muncul di bawah.\n\n"
                    . "Atau klik **Lanjutkan Tanpa Archive** — AI akan tetap berjalan "
                    . "tapi hasilnya mungkin kurang optimal karena tidak ada pola referensi."
                );
                // Kita tidak stop di sini — archive optional, analysis tetap lanjut
            } else {
                $this->msg($conversion, 'ai', 'info', "✅ {$successCount} archive jurnal berhasil dibaca!");
            }
        }

        // Tambahkan archive manual kalau ada
        $manualArchives = $conversion->files()->where('type', 'archive_manual')->get();
        foreach ($manualArchives as $archiveFile) {
            $content = $this->extractFileContent($archiveFile->fullPath(), $archiveFile->extension());
            if ($content) {
                $archiveContent .= "\n\n=== JURNAL: {$archiveFile->original_name} ===\n{$content}";
            }
        }

        return $archiveContent;
    }

    private function extractFileContent(string $fullPath, string $extension): ?string
    {
        try {
            if (!file_exists($fullPath)) {
                Log::error("File tidak ditemukan: {$fullPath}");
                return null;
            }

            if ($extension === 'pdf') {
                $parser = new PdfParser();
                $pdf    = $parser->parseFile($fullPath);
                return $pdf->getText();
            }

            if (in_array($extension, ['doc', 'docx'])) {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($fullPath);
                $text    = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        } elseif (method_exists($element, 'getElements')) {
                            foreach ($element->getElements() as $child) {
                                if (method_exists($child, 'getText')) {
                                    $text .= $child->getText() . ' ';
                                }
                            }
                            $text .= "\n";
                        }
                    }
                }
                return $text;
            }

            // Untuk format lain, coba baca sebagai teks biasa
            return file_get_contents($fullPath) ?: null;

        } catch (\Exception $e) {
            Log::error("ExtractFileContent error [{$extension}]: " . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS — Formatting & Messaging
    // ═══════════════════════════════════════════════════════════════════════════

    private function buildDiagnosisMessage(array $diagnosis): string
    {
        $scopeIcon = match ($diagnosis['scope_match'] ?? '') {
            'match'    => '✅',
            'partial'  => '⚠️',
            'mismatch' => '❌',
            default    => '❓',
        };

        $scopePct = $diagnosis['scope_match_percentage'] ?? 0;
        $scopeReason = $diagnosis['scope_match_reason'] ?? '-';

        // Judul recommendations
        $titlesText = '';
        foreach ($diagnosis['title_recommendations'] ?? [] as $i => $t) {
            $no = $i + 1;
            $titlesText .= "\n**Opsi {$no}:** {$t['title']}\n→ _{$t['reason']}_\n";
        }

        // Gap analysis
        $gap = $diagnosis['gap_analysis'] ?? [];

        $strengths  = implode("\n", array_map(fn($s) => "- ✅ {$s}", $gap['strengths'] ?? []));
        $toAdd      = implode("\n", array_map(fn($s) => "- ➕ {$s}", $gap['to_add'] ?? []));
        $toRemove   = implode("\n", array_map(fn($s) => "- ✂️ {$s}", $gap['to_remove'] ?? []));
        $toTransform = implode("\n", array_map(fn($s) => "- 🔄 {$s}", $gap['to_transform'] ?? []));

        // Compliance check
        $compliance = $diagnosis['compliance_check'] ?? [];
        $complianceText = '';
        foreach ($compliance as $aspect => $check) {
            $icon = match ($check['status'] ?? '') {
                'ok'      => '✅',
                'warning' => '⚠️',
                'error'   => '❌',
                default   => '❓',
            };
            $complianceText .= "- {$icon} **" . ucfirst($aspect) . "**: {$check['note']}\n";
        }

        return <<<MSG
## 📊 Diagnosis Report AI

### {$scopeIcon} Scope Match — {$scopePct}%
{$scopeReason}

---

### 📝 Rekomendasi Judul (pilih salah satu di bawah)
{$titlesText}

---

### 🔍 Gap Analysis

**Yang sudah bagus dari skripsimu:**
{$strengths}

**Yang perlu ditambahkan:**
{$toAdd}

**Yang perlu dipangkas/dihapus:**
{$toRemove}

**Yang perlu diubah format/gaya:**
{$toTransform}

---

### 📋 Compliance Check
{$complianceText}

---

Sekarang pilih judul yang kamu suka dan jawab pertanyaan AI di bawah ini, lalu klik **Mulai Konversi**!
MSG;
    }

    private function msg(Conversion $conversion, string $role, string $type, string $content): void
    {
        ConversionMessage::create([
            'conversion_id' => $conversion->id,
            'role'          => $role,
            'type'          => $type,
            'content'       => $content,
        ]);
    }

    private function failConversion(Conversion $conversion, string $message): void
    {
        $conversion->update([
            'status'        => Conversion::STATUS_FAILED,
            'error_message' => $message,
        ]);

        $this->msg(
            $conversion,
            'system',
            'error',
            "❌ **Proses gagal:** {$message}\n\nKamu bisa coba ulang atau hubungi support."
        );
    }
}