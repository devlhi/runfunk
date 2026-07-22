<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kolom role dibuat sebagai ENUM('peserta','panitia'), jadi nilai baru harus
 * didaftarkan dulu di tingkat basis data — menambah konstanta di model saja
 * tidak cukup, penyimpanannya akan ditolak MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('peserta','panitia','developer') NOT NULL DEFAULT 'peserta'");
    }

    public function down(): void
    {
        // Turunkan developer jadi panitia dulu supaya tidak ada baris yang
        // nilainya keluar dari daftar ENUM yang lebih sempit.
        DB::table('users')->where('role', 'developer')->update(['role' => 'panitia']);

        DB::statement("ALTER TABLE users MODIFY role ENUM('peserta','panitia') NOT NULL DEFAULT 'peserta'");
    }
};
