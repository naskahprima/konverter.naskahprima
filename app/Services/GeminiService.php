<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiService
 *
 * Menangani semua komunikasi dengan Google Gemini API.
 * Support konversi semua jenis naskah akademik:
 * skripsi, jurnal, paper, prosiding, artikel ilmiah, dll.
 */
class GeminiService
{
    private string $apiKey;
    private string $model   = 'gemini-1.5-pro';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** Threshold minimum scope match agar konversi bisa dilanjutkan */
    public const SCOPE_MATCH_THRESHOLD = 70;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
    }

    // ── Core HTTP Call ────────────────────────────────────────────────────────

    public function generate(string $prompt, array $options = []): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key tidak ditemukan. Set GEMINI_API_KEY di .env');
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->post("{$this->baseUrl}{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => $options['temperature'] ?? 0.7,
                        'maxOutputTokens' => $options['maxTokens']   ?? 8192,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }

            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('GeminiService exception: ' . $e->getMessage());
            return null;
        }
    }

    public function generateJson(string $prompt): ?array
    {
        $jsonPrompt = $prompt
            . "\n\n⚠️ PENTING: Balas HANYA dengan JSON yang valid."
            . " Jangan tambahkan teks lain, penjelasan, atau markdown code block (```).";

        $raw = $this->generate($jsonPrompt, ['temperature' => 0.3]);
        if (!$raw) return null;

        $cleaned = preg_replace('/```json\s*|\s*```/', '', trim($raw));

        try {
            return json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('Gagal parse JSON dari Gemini', ['raw' => $raw]);
            return null;
        }
    }

    // ── Domain-Specific Prompts ───────────────────────────────────────────────

    /**
     * PHASE 2A — Analisis Author Guide
     */
    public function analyzeAuthorGuide(string $content): ?array
    {
        $prompt = <<<PROMPT
Kamu adalah asisten akademik senior yang sangat berpengalaman menganalisis Author Guide jurnal ilmiah.

Berikut konten Author Guide dari jurnal target:
---
{$content}
---

Ekstrak semua informasi penting dan kembalikan dalam format JSON berikut:
{
    "journal_name": "nama jurnal",
    "publisher": "nama publisher/lembaga",
    "scope": "deskripsi singkat scope jurnal (2-3 kalimat)",
    "topics_accepted": ["topik1", "topik2", "topik3"],
    "article_types": ["tipe artikel yang diterima, misal: Research Article, Review, dll"],
    "structure": {
        "required_sections": ["Abstract", "Introduction", "Methodology", "Results", "Discussion", "Conclusion", "References"],
        "abstract_word_limit": 250,
        "total_word_min": 4000,
        "total_word_max": 7000,
        "keywords_min": 3,
        "keywords_max": 5
    },
    "citation_style": "APA | IEEE | Vancouver | Chicago | lainnya",
    "language": "Indonesia | English | Both",
    "figure_table_rules": "aturan gambar dan tabel secara singkat",
    "reference_min_count": 20,
    "reference_max_years_old": 10,
    "special_requirements": ["persyaratan khusus lainnya"]
}

Kalau informasi tidak tersedia, gunakan null.
PROMPT;

        return $this->generateJson($prompt);
    }

    /**
     * PHASE 2B — Analisis pola dari archive jurnal lolos
     */
    public function analyzeArchivePatterns(string $archiveContent): ?array
    {
        $prompt = <<<PROMPT
Kamu adalah reviewer jurnal senior yang ahli mengidentifikasi pola jurnal yang berhasil lolos publikasi.

Berikut konten beberapa jurnal/naskah yang sudah lolos di jurnal target:
---
{$archiveContent}
---

Analisis pola yang membuat jurnal-jurnal ini lolos, kembalikan dalam format JSON:
{
    "dominant_topics": ["topik yang paling sering muncul", "topik2", "topik3"],
    "title_patterns": [
        "Pola 1: 'Analisis [X] Menggunakan [Metode] pada [Konteks]'",
        "Pola 2: 'Pengaruh [X] terhadap [Y]: Studi Kasus [Z]'",
        "Pola 3: 'Implementasi [X] untuk Meningkatkan [Y]'"
    ],
    "title_avg_word_count": 12,
    "abstract_pattern": "deskripsi pola struktur abstract yang paling sering muncul",
    "dominant_methods": ["metode penelitian yang paling sering digunakan"],
    "avg_reference_count": 30,
    "preferred_reference_years": "2019-2024",
    "writing_tone": "formal-akademik | semi-formal | teknis",
    "common_keywords": ["keyword1", "keyword2", "keyword3"],
    "structural_notes": "catatan penting lain tentang pola penulisan"
}
PROMPT;

        return $this->generateJson($prompt);
    }

    /**
     * PHASE 2C — Ekstrak ringkasan naskah akademik (skripsi, jurnal, paper, dll)
     */
    public function extractThesisSummary(string $documentContent): ?array
    {
        $prompt = <<<PROMPT
Kamu adalah asisten akademik yang ahli membaca dan meringkas berbagai jenis naskah akademik
(skripsi, tesis, jurnal, paper, prosiding, artikel ilmiah, dsb).

Berikut konten naskah akademik yang akan dikonversi:
---
{$documentContent}
---

Ekstrak semua informasi penting dari naskah ini, kembalikan dalam format JSON:
{
    "document_type": "skripsi | tesis | jurnal | paper | prosiding | artikel | laporan_penelitian | lainnya",
    "original_title": "judul asli naskah",
    "study_domain": "bidang ilmu, misal: Computer Science, Education, Public Health",
    "main_topic": "topik utama penelitian",
    "sub_topics": ["sub-topik1", "sub-topik2", "sub-topik3"],
    "research_problem": "rumusan masalah utama (1-2 kalimat)",
    "objectives": ["tujuan penelitian 1", "tujuan 2"],
    "methodology": "metode penelitian yang digunakan",
    "population_sample": "populasi dan sampel penelitian (kalau ada)",
    "key_findings": ["temuan utama 1", "temuan utama 2", "temuan utama 3"],
    "main_contribution": "kontribusi utama atau novelty penelitian ini",
    "estimated_references_count": 50,
    "has_quantitative_data": true,
    "has_qualitative_data": false
}
PROMPT;

        return $this->generateJson($prompt);
    }

    /**
     * PHASE 3 — Generate Diagnosis Report lengkap
     * Termasuk scope_match_percentage yang digunakan untuk validasi kelayakan
     */
    public function generateDiagnosisReport(
        array $authorGuideAnalysis,
        array $archiveAnalysis,
        array $documentSummary
    ): ?array {
        $agJson      = $this->toJson($authorGuideAnalysis);
        $archiveJson = $this->toJson($archiveAnalysis);
        $docJson     = $this->toJson($documentSummary);
        $threshold   = self::SCOPE_MATCH_THRESHOLD;

        $prompt = <<<PROMPT
Kamu adalah reviewer jurnal senior yang berpengalaman dan sangat jujur.

Kamu memiliki tiga sumber informasi:

1. ANALISIS AUTHOR GUIDE JURNAL TARGET:
{$agJson}

2. POLA JURNAL YANG SUDAH LOLOS DI JURNAL INI:
{$archiveJson}

3. RINGKASAN NASKAH AKADEMIK YANG AKAN DIKONVERSI:
{$docJson}

Berikan diagnosis lengkap. Kembalikan dalam format JSON:
{
    "scope_match": "match | partial | mismatch",
    "scope_match_percentage": 85,
    "scope_match_reason": "penjelasan singkat kenapa cocok/kurang cocok/tidak cocok (3-4 kalimat)",
    "rejection_reason": null,
    "alternative_journal_suggestions": [],
    "title_recommendations": [
        {
            "title": "Judul Rekomendasi 1 (sesuai pola archive & author guide)",
            "reason": "kenapa judul ini cocok untuk jurnal ini"
        },
        {
            "title": "Judul Rekomendasi 2",
            "reason": "alasannya"
        },
        {
            "title": "Judul Rekomendasi 3",
            "reason": "alasannya"
        }
    ],
    "gap_analysis": {
        "strengths": ["hal yang sudah bagus dari naskah dan cocok untuk jurnal ini"],
        "to_add": ["hal yang perlu ditambahkan agar lolos reviewer"],
        "to_remove": ["hal yang perlu dihapus atau dipangkas karena tidak relevan"],
        "to_transform": ["bagian yang perlu diubah format/gaya penulisannya"]
    },
    "compliance_check": {
        "structure":      { "status": "ok | warning | error", "note": "penjelasan singkat" },
        "word_count":     { "status": "ok | warning | error", "note": "penjelasan" },
        "citation_style": { "status": "ok | warning | error", "note": "penjelasan" },
        "language":       { "status": "ok | warning | error", "note": "penjelasan" },
        "references":     { "status": "ok | warning | error", "note": "penjelasan" }
    },
    "smart_questions": [
        {
            "id": "q1",
            "question": "pertanyaan spesifik yang BENAR-BENAR tidak bisa disimpulkan dari dokumen yang ada",
            "why_needed": "kenapa pertanyaan ini penting untuk kualitas hasil konversi"
        }
    ]
}

ATURAN PENTING untuk scope_match_percentage:
- Berikan angka yang JUJUR dan AKURAT berdasarkan kecocokan topik, metodologi, dan bidang ilmu
- Kalau scope_match = "mismatch" → scope_match_percentage HARUS di bawah {$threshold}
- Kalau scope_match = "match" → scope_match_percentage HARUS di atas 75
- Kalau scope_match = "partial" → scope_match_percentage antara 50-75

ATURAN untuk rejection_reason dan alternative_journal_suggestions:
- Jika scope_match_percentage < {$threshold}: ISI rejection_reason dengan penjelasan detail (2-3 paragraf) kenapa naskah ini TIDAK COCOK untuk jurnal target
- Jika scope_match_percentage < {$threshold}: ISI alternative_journal_suggestions dengan 3-5 NAMA JURNAL NYATA yang lebih cocok untuk naskah ini (beserta alasannya singkat)
- Jika scope_match_percentage >= {$threshold}: biarkan rejection_reason = null dan alternative_journal_suggestions = []

ATURAN untuk smart_questions:
- Maksimal 3 pertanyaan
- Hanya tanya hal yang TIDAK BISA kamu simpulkan sendiri dari dokumen
- Jangan tanya jika scope_match_percentage < {$threshold} (tidak perlu, karena akan ditolak)
PROMPT;

        return $this->generateJson($prompt);
    }

    /**
     * PHASE 5 — Generate konten jurnal final
     */
    public function generateJournalContent(
        string $documentContent,
        array  $authorGuideAnalysis,
        string $selectedTitle,
        array  $diagnosisReport,
        array  $qaAnswers = []
    ): ?array {
        $agJson         = $this->toJson($authorGuideAnalysis);
        $diagJson       = $this->toJson($diagnosisReport['gap_analysis'] ?? []);
        $complianceJson = $this->toJson($diagnosisReport['compliance_check'] ?? []);

        $qaText = '';
        if (!empty($qaAnswers)) {
            $qaText = "INFORMASI TAMBAHAN DARI USER (Jawaban Q&A):\n";
            foreach ($qaAnswers as $question => $answer) {
                if ($question === 'selected_title') continue;
                $qaText .= "- {$question}: {$answer}\n";
            }
        }

        $prompt = <<<PROMPT
Kamu adalah penulis akademik profesional yang ahli mengkonversi berbagai jenis naskah akademik
(skripsi, jurnal lama, paper, prosiding, dll) menjadi artikel jurnal ilmiah baru yang siap submit.

JUDUL YANG DIPILIH USER: {$selectedTitle}

PANDUAN AUTHOR GUIDE (wajib dipatuhi):
{$agJson}

REKOMENDASI DARI DIAGNOSIS AI:
Gap Analysis: {$diagJson}
Compliance Issues: {$complianceJson}

{$qaText}

KONTEN NASKAH SUMBER:
---
{$documentContent}
---

Tugas kamu:
1. Konversikan naskah menjadi artikel jurnal yang SEPENUHNYA sesuai author guide
2. Sesuaikan gaya bahasa, struktur, dan format
3. Padatkan konten yang terlalu panjang, tambahkan yang kurang
4. Pastikan sitasi menggunakan format yang diminta author guide

Kembalikan dalam format JSON:
{
    "title": "judul final yang dipilih",
    "abstract": "abstract 150-250 kata sesuai panduan",
    "keywords": ["keyword1", "keyword2", "keyword3", "keyword4", "keyword5"],
    "introduction": "Isi pendahuluan yang sudah diformat untuk jurnal",
    "literature_review": "Tinjauan pustaka yang relevan dan terkini (atau null jika tidak wajib)",
    "methodology": "Metodologi yang sudah disesuaikan dengan format jurnal",
    "results": "Hasil penelitian yang disajikan secara akademik",
    "discussion": "Pembahasan yang menghubungkan temuan dengan literatur",
    "conclusion": "Kesimpulan yang ringkas dan impactful",
    "references": [
        "Penulis, A. (Tahun). Judul artikel. Nama Jurnal, Vol(No), hal. DOI"
    ],
    "submission_checklist": [
        "✅ Judul sudah sesuai pola yang direkomendasikan",
        "✅ Abstract dalam batas kata yang ditentukan",
        "✅ Semua section wajib sudah ada",
        "✅ Format sitasi sudah sesuai author guide",
        "⚠️ Cek kembali jumlah referensi — minimal X referensi",
        "⚠️ Pastikan semua referensi tidak lebih dari X tahun terakhir"
    ]
}
PROMPT;

        return $this->generateJson($prompt);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}