<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UrlReaderService
 *
 * Membaca konten dari URL dan mengekstrak teks yang bisa dibaca AI.
 * Kalau URL gagal diakses, return null → trigger graceful fallback ke user.
 */
class UrlReaderService
{
    private const MAX_CONTENT_LENGTH = 50000; // karakter, aman untuk Gemini context
    private const TIMEOUT_SECONDS    = 30;

    // ── Public Methods ────────────────────────────────────────────────────────

    /**
     * Baca konten dari satu URL.
     * Return null kalau gagal (caller wajib handle fallback-nya).
     */
    public function read(string $url): ?string
    {
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::warning("UrlReaderService: URL tidak valid — {$url}");
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id,en;q=0.5',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("UrlReaderService: HTTP {$response->status()} untuk {$url}");
                return null;
            }

            $contentType = $response->header('Content-Type', '');

            // Kalau responsenya PDF (beberapa jurnal langsung serve PDF)
            if (str_contains($contentType, 'application/pdf')) {
                Log::info("UrlReaderService: URL mengarah ke PDF langsung — {$url}");
                // Untuk PDF dari URL, kita tidak bisa parse langsung
                // Return signal khusus agar caller tahu ini PDF
                return '__PDF_URL__:' . $url;
            }

            return $this->extractTextFromHtml($response->body());

        } catch (\Exception $e) {
            Log::warning("UrlReaderService exception untuk {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Baca beberapa URL sekaligus.
     * Return array dengan status success/fail per URL.
     */
    public function readMultiple(array $urls): array
    {
        $results = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) continue;

            $content = $this->read($url);
            $results[] = [
                'url'     => $url,
                'content' => $content,
                'success' => $content !== null,
                'is_pdf'  => $content !== null && str_starts_with($content, '__PDF_URL__:'),
            ];
        }

        return $results;
    }

    /**
     * Hitung berapa banyak URL yang berhasil di-fetch.
     */
    public function countSuccess(array $readResults): int
    {
        return count(array_filter($readResults, fn($r) => $r['success'] && !$r['is_pdf']));
    }

    /**
     * Gabungkan konten dari semua URL yang berhasil di-read.
     */
    public function mergeContent(array $readResults): string
    {
        $combined = '';

        foreach ($readResults as $result) {
            if ($result['success'] && !$result['is_pdf'] && $result['content']) {
                $combined .= "\n\n=== KONTEN DARI: {$result['url']} ===\n";
                $combined .= $result['content'];
            }
        }

        return trim($combined);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Ekstrak teks yang readable dari HTML.
     * Buang script, style, nav, footer agar teksnya bersih.
     */
    private function extractTextFromHtml(string $html): string
    {
        // Buang tag yang tidak relevan
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<style[^>]*>.*?<\/style>/is',
            '/<nav[^>]*>.*?<\/nav>/is',
            '/<footer[^>]*>.*?<\/footer>/is',
            '/<header[^>]*>.*?<\/header>/is',
            '/<aside[^>]*>.*?<\/aside>/is',
            '/<!--.*?-->/s',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        // Tambahkan spasi untuk block elements
        $html = preg_replace('/<(br|p|div|h[1-6]|li|tr)[^>]*>/i', "\n", $html);

        // Strip semua tag HTML
        $text = strip_tags($html);

        // Bersihkan whitespace berlebih
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Batasi panjang konten
        return mb_substr($text, 0, self::MAX_CONTENT_LENGTH);
    }
}
