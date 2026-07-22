<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan acara yang sebelumnya hanya ada di .env — tanggal, lokasi, dan
 * rekening pembayaran. Dipindah ke database supaya developer bisa mengubahnya
 * lewat antarmuka tanpa menyentuh berkas dan me-restart aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
