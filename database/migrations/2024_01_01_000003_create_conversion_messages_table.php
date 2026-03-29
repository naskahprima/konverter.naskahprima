<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Role  : system | ai | user
     * Type  : info | fallback_request | qa | diagnosis | success | error
     */
    public function up(): void
    {
        Schema::create('conversion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_id')->constrained()->onDelete('cascade');
            $table->string('role');  // system | ai | user
            $table->string('type')->default('info'); // info | fallback_request | qa | diagnosis | success | error
            $table->longText('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_messages');
    }
};
