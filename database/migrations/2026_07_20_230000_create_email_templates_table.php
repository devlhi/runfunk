<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isi email yang bisa disunting developer lewat antarmuka.
 *
 * Yang disimpan hanya BADAN email — kerangka, tanda tangan, dan kakinya tetap
 * berkas Blade yang tidak bisa disentuh dari peramban. Alasannya keamanan:
 * Blade dikompilasi jadi PHP, jadi kalau isi dari formulir ikut dikompilasi,
 * siapa pun yang bisa membuka halaman ini bisa menjalankan kode di server.
 * Isinya diperlakukan sebagai HTML biasa dan disaring lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('subject', 200);
            $table->text('body_html');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
