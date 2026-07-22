<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor versi kartu panitia.
 *
 * Tanda tangan pada QR kartu ikut memuat angka ini. Menaikkannya lewat "cetak
 * ulang" membuat kartu lama langsung ditolak saat dipindai — satu-satunya cara
 * menonaktifkan kartu yang hilang atau tertinggal di tangan orang yang sudah
 * tidak jadi panitia, tanpa harus menghapus akunnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('card_version')->default(1)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('card_version');
        });
    }
};
