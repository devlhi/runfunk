<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menuliskan singkatan "IKA" jadi bentuk panjangnya pada jabatan ketua.
 *
 * Mengubah nilai bawaan di App\Models\Setting saja tidak cukup: begitu panel
 * pengaturan pernah disimpan, nilainya sudah menetap di tabel settings dan
 * bawaan itu tidak pernah dibaca lagi. Jadi baris yang sudah ada ikut
 * diperbarui di sini.
 *
 * Hanya menyentuh baris yang masih berisi teks lama — kalau panitia sudah
 * menggantinya sendiri dengan tulisan lain, pilihan mereka tidak ditimpa.
 */
return new class extends Migration
{
    private const LAMA = 'Ketua IKA SMK Gotong Royong Telaga';

    private const BARU = 'Ketua Ikatan Keluarga Alumni SMK Gotong Royong';

    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'chairman_title')
            ->where('value', self::LAMA)
            ->update(['value' => self::BARU]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'chairman_title')
            ->where('value', self::BARU)
            ->update(['value' => self::LAMA]);
    }
};
