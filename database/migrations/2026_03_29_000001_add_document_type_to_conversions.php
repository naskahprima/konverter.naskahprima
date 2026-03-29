<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom untuk support semua jenis naskah akademik:
     * - document_type: tipe naskah (skripsi, jurnal, paper, dll)
     * - document_content: alias untuk thesis_content (lebih generic)
     *
     * Status 'rejected' tidak perlu kolom tambahan,
     * cukup di-handle di level aplikasi.
     */
    public function up(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            // Tipe naskah sumber
            $table->string('document_type')->default('skripsi')->after('user_id');

            // document_content sebagai alias thesis_content (thesis_content tetap ada untuk BC)
            // Kita tambah virtual/alias di model, tidak perlu kolom baru jika thesis_content sudah ada
            // Tapi kalau mau explicit, tambah ini:
            // $table->longText('document_content')->nullable()->after('thesis_content');
        });
    }

    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
