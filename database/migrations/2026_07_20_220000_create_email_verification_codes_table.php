<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kode verifikasi email pendaftar.
 *
 * Disimpan terhash, bukan apa adanya: kalau basis data sampai bocor, kode yang
 * masih berlaku tidak bisa langsung dipakai orang lain untuk mengaktifkan akun.
 * Satu baris per pengguna — meminta kode baru menimpa yang lama, sehingga tidak
 * pernah ada dua kode yang sama-sama sah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            // Pembatas tebak-tebakan. Kode 6 angka hanya punya sejuta kemungkinan,
            // jadi tanpa ini bisa dijebol dengan percobaan beruntun.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Akun yang sudah ada dianggap terverifikasi. Tanpa ini semua peserta dan
        // panitia yang terdaftar sebelum fitur ini ada langsung terkunci dari
        // pendaftaran lomba, padahal mereka tidak melakukan apa pun yang salah.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
