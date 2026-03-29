<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status flow:
     * pending → analyzing → waiting_fallback → awaiting_qa → converting → completed | failed
     */
    public function up(): void
    {
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // ── Status ──────────────────────────────────────────────────
            $table->string('status')->default('pending');

            // ── Input dari User ─────────────────────────────────────────
            $table->text('author_guide_url')->nullable();
            $table->json('archive_urls')->nullable(); // array of URLs

            // ── Konten yang berhasil difetch / diekstrak ─────────────────
            $table->longText('author_guide_content')->nullable();
            $table->longText('archive_content')->nullable();
            $table->longText('thesis_content')->nullable();

            // ── Hasil Analisis AI ────────────────────────────────────────
            $table->string('scope_match')->nullable();       // match | partial | mismatch
            $table->text('scope_match_reason')->nullable();
            $table->json('title_recommendations')->nullable(); // 3 opsi judul
            $table->longText('gap_analysis')->nullable();
            $table->json('compliance_check')->nullable();
            $table->longText('diagnosis_report')->nullable(); // raw JSON dari AI

            // ── Smart Q&A ────────────────────────────────────────────────
            $table->json('qa_questions')->nullable();
            $table->json('qa_answers')->nullable();

            // ── Fallback Flags ───────────────────────────────────────────
            $table->boolean('author_guide_fallback')->default(false);
            $table->boolean('archive_fallback')->default(false);

            // ── Output ───────────────────────────────────────────────────
            $table->string('output_path')->nullable();
            $table->longText('submission_checklist')->nullable();

            // ── Error ────────────────────────────────────────────────────
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};
