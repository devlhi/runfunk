<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi nama Ketua IKA pada sambutan di halaman depan.
 *
 * Sebelumnya sengaja dikosongkan supaya yang tampil hanya jabatannya — lebih
 * baik begitu daripada memasang nama orang yang keliru. Namanya kini diketahui,
 * jadi barisnya diisi.
 *
 * Hanya menyentuh baris yang masih kosong: kalau panitia sudah mengisinya
 * sendiri lewat panel, isian mereka tidak ditimpa.
 *
 * Barisnya diperbarui, TIDAK pernah disisipkan. Tabel settings sengaja dibiarkan
 * kosong sampai panel pengaturan benar-benar disimpan — selama kosong, nilainya
 * diambil dari bawaan di App\Models\Setting (yang juga sudah berisi nama ini).
 * Menyisipkan baris di sini akan mematahkan aturan itu pada pemasangan baru.
 */
return new class extends Migration
{
    private const NAMA = 'Malik Mahmud';

    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'chairman_name')
            ->where(fn ($q) => $q->whereNull('value')->orWhere('value', ''))
            ->update(['value' => self::NAMA]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'chairman_name')
            ->where('value', self::NAMA)
            ->update(['value' => '']);
    }
};
