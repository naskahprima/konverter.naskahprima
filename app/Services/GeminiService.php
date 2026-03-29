<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model  = 'gemini-2.5-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
    }

    public function generate(string $prompt, array $options = []): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key tidak ditemukan.');
            return null;
        }
        try {
            $response = Http::timeout(300)
                ->post("{$this->baseUrl}{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature'     => $options['temperature'] ?? 0.7,
                        'maxOutputTokens' => $options['maxTokens']   ?? 65536,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('GeminiService exception: ' . $e->getMessage());
            return null;
        }
    }

    public function generateJson(string $prompt): ?array
    {
        $jsonPrompt = $prompt . "\n\nPENTING: Balas HANYA dengan JSON yang valid. Jangan tambahkan teks lain atau markdown code block.";
        $raw = $this->generate($jsonPrompt, ['temperature' => 0.3]);
        if (!$raw) return null;

        $cleaned = preg_replace('/^```[a-z]*\s*/m', '', trim($raw));
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        try {
            return json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('Gagal parse JSON dari Gemini', ['raw' => substr($raw, 0, 500)]);
            return null;
        }
    }

    public function analyzeAuthorGuide(string $content): ?array
    {
        $prompt = <<<PROMPT
Kamu adalah asisten akademik senior yang berpengalaman menganalisis Author Guide jurnal ilmiah.

Berikut konten Author Guide dari jurnal target:
---
{$content}
---

Ekstrak informasi penting dan kembalikan dalam format JSON:
{
    "journal_name": "nama jurnal",
    "publisher": "nama publisher",
    "scope": "deskripsi scope jurnal",
    "topics_accepted": ["topik1", "topik2"],
    "article_types": ["tipe artikel"],
    "structure": {
        "required_sections": ["Abstract", "Introduction", "Methodology", "Results", "Discussion", "Conclusion", "References"],
        "abstract_word_limit": 250,
        "total_word_min": 4000,
        "total_word_max": 7000,
        "keywords_min": 3,
        "keywords_max": 5
    },
    "citation_style": "APA",
    "language": "Indonesia",
    "figure_table_rules": "aturan gambar dan tabel",
    "reference_min_count": 20,
    "reference_max_years_old": 10,
    "special_requirements": ["persyaratan khusus"]
}
Kalau informasi tidak tersedia, gunakan null.
PROMPT;
        return $this->generateJson($prompt);
    }

    public function analyzeArchivePatterns(string $archiveContent): ?array
    {
        $prompt = <<<PROMPT
Kamu adalah reviewer jurnal senior yang ahli mengidentifikasi pola jurnal yang lolos publikasi.

Berikut konten jurnal yang sudah lolos:
---
{$archiveContent}
---

Analisis pola, kembalikan dalam format JSON:
{
    "dominant_topics": ["topik1", "topik2"],
    "title_patterns": ["Pola 1", "Pola 2"],
    "title_avg_word_count": 12,
    "abstract_pattern": "pola struktur abstract",
    "dominant_methods": ["metode1"],
    "avg_reference_count": 30,
    "preferred_reference_years": "2019-2024",
    "writing_tone": "formal-akademik",
    "common_keywords": ["keyword1"],
    "structural_notes": "catatan penting"
}
PROMPT;
        return $this->generateJson($prompt);
    }

    public function extractThesisSummary(string $thesisContent): ?array
    {
        $prompt = <<<PROMPT
Kamu adalah asisten akademik yang ahli meringkas skripsi.

Berikut konten skripsi:
---
{$thesisContent}
---

Ekstrak informasi penting, kembalikan dalam format JSON:
{
    "original_title": "judul asli",
    "study_domain": "bidang ilmu",
    "main_topic": "topik utama",
    "sub_topics": ["sub-topik1"],
    "research_problem": "rumusan masalah",
    "objectives": ["tujuan1"],
    "methodology": "metode penelitian",
    "population_sample": "populasi dan sampel",
    "key_findings": ["temuan1"],
    "main_contribution": "kontribusi utama",
    "estimated_references_count": 50,
    "has_quantitative_data": true,
    "has_qualitative_data": false
}
PROMPT;
        return $this->generateJson($prompt);
    }

    public function generateDiagnosisReport(array $authorGuideAnalysis, array $archiveAnalysis, array $thesisSummary): ?array
    {
        $agJson      = $this->toJson($authorGuideAnalysis);
        $archiveJson = $this->toJson($archiveAnalysis);
        $thesisJson  = $this->toJson($thesisSummary);

        $prompt = <<<PROMPT
Kamu adalah reviewer jurnal senior yang jujur.

1. ANALISIS AUTHOR GUIDE:
{$agJson}

2. POLA JURNAL LOLOS:
{$archiveJson}

3. RINGKASAN SKRIPSI:
{$thesisJson}

Berikan diagnosis lengkap dalam format JSON:
{
    "scope_match": "match | partial | mismatch",
    "scope_match_percentage": 85,
    "scope_match_reason": "penjelasan 3-4 kalimat",
    "title_recommendations": [
        {"title": "Judul 1", "reason": "alasan"},
        {"title": "Judul 2", "reason": "alasan"},
        {"title": "Judul 3", "reason": "alasan"}
    ],
    "gap_analysis": {
        "strengths": ["kekuatan1"],
        "to_add": ["yang perlu ditambah"],
        "to_remove": ["yang perlu dihapus"],
        "to_transform": ["yang perlu diubah"]
    },
    "compliance_check": {
        "structure":      {"status": "ok", "note": "penjelasan"},
        "word_count":     {"status": "ok", "note": "penjelasan"},
        "citation_style": {"status": "ok", "note": "penjelasan"},
        "language":       {"status": "ok", "note": "penjelasan"},
        "references":     {"status": "ok", "note": "penjelasan"}
    },
    "smart_questions": [
        {"id": "q1", "question": "pertanyaan", "why_needed": "alasan"}
    ]
}
PROMPT;
        return $this->generateJson($prompt);
    }

    public function generateJournalContent(string $thesisContent, array $authorGuideAnalysis, string $selectedTitle, array $diagnosisReport, array $qaAnswers = []): ?array
    {
        $agJson         = $this->toJson($authorGuideAnalysis);
        $diagJson       = $this->toJson($diagnosisReport['gap_analysis'] ?? []);
        $complianceJson = $this->toJson($diagnosisReport['compliance_check'] ?? []);

        $qaText = '';
        if (!empty($qaAnswers)) {
            $qaText = "INFORMASI TAMBAHAN DARI USER:\n";
            foreach ($qaAnswers as $q => $a) {
                $qaText .= "- {$q}: {$a}\n";
            }
        }

        $prompt = <<<PROMPT
Kamu adalah penulis akademik profesional yang ahli mengkonversi skripsi menjadi jurnal ilmiah.

JUDUL YANG DIPILIH: {$selectedTitle}

AUTHOR GUIDE (wajib dipatuhi):
{$agJson}

GAP ANALYSIS: {$diagJson}
COMPLIANCE: {$complianceJson}

{$qaText}

KONTEN SUMBER:
---
{$thesisContent}
---

Konversikan menjadi artikel jurnal. Kembalikan dalam format JSON:
{
    "title": "judul final",
    "abstract": "abstract 150-250 kata",
    "keywords": ["keyword1", "keyword2", "keyword3"],
    "introduction": "isi pendahuluan",
    "literature_review": "tinjauan pustaka",
    "methodology": "metodologi",
    "results": "hasil penelitian",
    "discussion": "pembahasan",
    "conclusion": "kesimpulan",
    "references": ["Referensi 1", "Referensi 2"],
    "submission_checklist": ["item1", "item2"]
}
PROMPT;
        return $this->generateJson($prompt);
    }

    private function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}