<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type values:
     *   skripsi            → File skripsi utama user
     *   template           → Template .docx dari jurnal target
     *   author_guide_manual → Author guide yang diupload manual (fallback)
     *   archive_manual     → Contoh jurnal lolos yang diupload manual (fallback)
     */
    public function up(): void
    {
        Schema::create('conversion_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_id')->constrained()->onDelete('cascade');
            $table->string('type'); // skripsi | template | author_guide_manual | archive_manual
            $table->string('original_name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->bigInteger('size')->nullable(); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_files');
    }
};
