<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversion extends Model
{
    protected $fillable = [
        'user_id', 'status',
        'document_type',
        'author_guide_url', 'archive_urls',
        'author_guide_content', 'archive_content', 'document_content',
        // legacy field, kept for BC
        'thesis_content',
        'scope_match', 'scope_match_reason',
        'title_recommendations', 'gap_analysis',
        'compliance_check', 'diagnosis_report',
        'qa_questions', 'qa_answers',
        'author_guide_fallback', 'archive_fallback',
        'output_path', 'submission_checklist',
        'error_message',
    ];

    protected $casts = [
        'archive_urls'          => 'array',
        'title_recommendations' => 'array',
        'compliance_check'      => 'array',
        'qa_questions'          => 'array',
        'qa_answers'            => 'array',
        'author_guide_fallback' => 'boolean',
        'archive_fallback'      => 'boolean',
    ];

    // ── Status Constants ─────────────────────────────────────────────────────
    const STATUS_PENDING          = 'pending';
    const STATUS_ANALYZING        = 'analyzing';
    const STATUS_WAITING_FALLBACK = 'waiting_fallback';
    const STATUS_REJECTED         = 'rejected';   // scope match < threshold
    const STATUS_AWAITING_QA      = 'awaiting_qa';
    const STATUS_CONVERTING       = 'converting';
    const STATUS_COMPLETED        = 'completed';
    const STATUS_FAILED           = 'failed';

    // ── Relationships ────────────────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ConversionFile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversionMessage::class)->orderBy('created_at');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function sourceFile(): ?ConversionFile
    {
        // Support both old 'skripsi' and new 'naskah' type
        return $this->files()->whereIn('type', ['skripsi', 'naskah'])->first();
    }

    /** @deprecated use sourceFile() */
    public function skripsiFile(): ?ConversionFile
    {
        return $this->sourceFile();
    }

    public function templateFile(): ?ConversionFile
    {
        return $this->files()->where('type', 'template')->first();
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_ANALYZING,
            self::STATUS_CONVERTING,
        ]);
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function needsUserAction(): bool
    {
        return in_array($this->status, [
            self::STATUS_WAITING_FALLBACK,
            self::STATUS_AWAITING_QA,
        ]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING          => '⏳ Menunggu proses...',
            self::STATUS_ANALYZING        => '🔍 Sedang dianalisis AI...',
            self::STATUS_WAITING_FALLBACK => '⚠️ Butuh file tambahan dari kamu',
            self::STATUS_REJECTED         => '🚫 Naskah tidak sesuai scope jurnal',
            self::STATUS_AWAITING_QA      => '💬 AI punya pertanyaan untukmu',
            self::STATUS_CONVERTING       => '✍️ AI sedang menulis jurnal...',
            self::STATUS_COMPLETED        => '✅ Selesai! Jurnal siap didownload',
            self::STATUS_FAILED           => '❌ Terjadi kesalahan',
            default                       => '❓ Unknown',
        };
    }

    /**
     * Ambil scope match percentage dari diagnosis report.
     */
    public function getScopeMatchPercentage(): int
    {
        $diagnosis = json_decode($this->diagnosis_report ?? '{}', true);
        return (int) ($diagnosis['scope_match_percentage'] ?? 0);
    }

    /**
     * Ambil rejection reason dari diagnosis report.
     */
    public function getRejectionReason(): ?string
    {
        $diagnosis = json_decode($this->diagnosis_report ?? '{}', true);
        return $diagnosis['rejection_reason'] ?? null;
    }

    /**
     * Ambil saran jurnal alternatif dari diagnosis report.
     */
    public function getAlternativeJournals(): array
    {
        $diagnosis = json_decode($this->diagnosis_report ?? '{}', true);
        return $diagnosis['alternative_journal_suggestions'] ?? [];
    }
}