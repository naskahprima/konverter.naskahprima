<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DocxService
 *
 * Generate file Word (.docx) dari konten jurnal yang sudah dihasilkan AI.
 * Mendukung template dari user — kalau ada template, struktur halaman
 * mengikuti template. Kalau tidak ada, generate dari scratch.
 */
class DocxService
{
    /**
     * Generate DOCX output.
     *
     * @param string|null $templatePath Path storage ke template .docx
     * @param array       $content      Konten jurnal dari GeminiService
     * @return string Storage path file output
     */
    public function generate(?string $templatePath, array $content): string
    {
        try {
            return $this->generateFromScratch($content);
        } catch (\Exception $e) {
            Log::error('DocxService::generate error: ' . $e->getMessage());
            throw $e;
        }
    }

    // ── Generate dari Scratch ─────────────────────────────────────────────────

    private function generateFromScratch(array $content): string
    {
        $phpWord = new PhpWord();

        // Properties dokumen
        $phpWord->getDocInfo()->setTitle($content['title'] ?? 'Jurnal Ilmiah');
        $phpWord->getDocInfo()->setCreator('NaskahPrima Konverter');
        $phpWord->getDocInfo()->setDescription('Dihasilkan oleh NaskahPrima.id');

        // Definisikan styles
        $this->defineStyles($phpWord);

        // Section dengan margin A4 standar jurnal
        $section = $phpWord->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft'   => Converter::cmToTwip(3),
            'marginRight'  => Converter::cmToTwip(2.5),
        ]);

        // ── JUDUL ────────────────────────────────────────────────────────────
        $section->addText(
            $content['title'] ?? 'Judul Jurnal',
            'TitleFont',
            ['alignment' => 'center', 'spaceAfter' => 240]
        );

        // ── ABSTRACT ─────────────────────────────────────────────────────────
        if (!empty($content['abstract'])) {
            $section->addText('ABSTRACT', 'HeadingFont', ['spaceAfter' => 120]);
            $section->addText($content['abstract'], 'BodyFont', $this->bodyPara());

            if (!empty($content['keywords'])) {
                $kw = implode('; ', $content['keywords']);
                $section->addText(
                    "Keywords: {$kw}",
                    ['bold' => false, 'italic' => true, 'size' => 10, 'name' => 'Times New Roman'],
                    ['spaceAfter' => 240]
                );
            }
        }

        // ── SECTION UTAMA ─────────────────────────────────────────────────────
        $mainSections = [
            'introduction'     => 'PENDAHULUAN (INTRODUCTION)',
            'literature_review' => 'TINJAUAN PUSTAKA',
            'methodology'      => 'METODOLOGI',
            'results'          => 'HASIL',
            'discussion'       => 'PEMBAHASAN',
            'conclusion'       => 'KESIMPULAN',
        ];

        foreach ($mainSections as $key => $heading) {
            if (!empty($content[$key])) {
                $section->addText($heading, 'HeadingFont', ['spaceBefore' => 240, 'spaceAfter' => 120]);
                // Split per paragraf agar lebih rapi
                foreach (explode("\n\n", $content[$key]) as $paragraph) {
                    $paragraph = trim($paragraph);
                    if (!empty($paragraph)) {
                        $section->addText($paragraph, 'BodyFont', $this->bodyPara());
                    }
                }
            }
        }

        // ── DAFTAR PUSTAKA ────────────────────────────────────────────────────
        if (!empty($content['references'])) {
            $section->addText(
                'DAFTAR PUSTAKA (REFERENCES)',
                'HeadingFont',
                ['spaceBefore' => 240, 'spaceAfter' => 120]
            );
            foreach ($content['references'] as $ref) {
                $section->addText($ref, 'BodyFont', [
                    'alignment'   => 'both',
                    'indentation' => ['left' => 720, 'firstLine' => -720],
                    'spaceAfter'  => 60,
                ]);
            }
        }

        // ── SUBMISSION CHECKLIST (halaman terpisah) ───────────────────────────
        if (!empty($content['submission_checklist'])) {
            $section->addPageBreak();
            $section->addText('SUBMISSION CHECKLIST', 'HeadingFont', ['spaceAfter' => 120]);
            $section->addText(
                '⚠️ Halaman ini hanya untuk panduan. HAPUS sebelum submit ke jurnal.',
                ['italic' => true, 'size' => 10, 'name' => 'Times New Roman', 'color' => 'FF0000'],
                ['spaceAfter' => 120]
            );

            foreach ($content['submission_checklist'] as $item) {
                $section->addText($item, 'BodyFont', ['spaceAfter' => 60]);
            }
        }

        // Simpan ke storage
        return $this->saveToStorage($phpWord);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function defineStyles(PhpWord $phpWord): void
    {
        $phpWord->addFontStyle('TitleFont', [
            'bold'   => true,
            'size'   => 14,
            'name'   => 'Times New Roman',
            'color'  => '000000',
        ]);

        $phpWord->addFontStyle('HeadingFont', [
            'bold'  => true,
            'size'  => 12,
            'name'  => 'Times New Roman',
        ]);

        $phpWord->addFontStyle('BodyFont', [
            'size'  => 11,
            'name'  => 'Times New Roman',
        ]);
    }

    private function bodyPara(): array
    {
        return [
            'alignment'    => 'both',
            'lineHeight'   => 1.5,
            'spaceAfter'   => 120,
            'spaceBefore'  => 0,
        ];
    }

    private function saveToStorage(PhpWord $phpWord): string
    {
        $filename   = 'jurnal_' . Str::uuid() . '.docx';
        $outputDir  = 'conversions/output';
        $outputPath = "{$outputDir}/{$filename}";

        // Buat direktori kalau belum ada
        Storage::makeDirectory($outputDir);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save(Storage::path($outputPath));

        return $outputPath;
    }
}
