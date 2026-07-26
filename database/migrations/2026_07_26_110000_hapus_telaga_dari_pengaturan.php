<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus kata "Telaga" dari nama sekolah pada pengaturan yang sudah tersimpan.
 *
 * Sama seperti jabatan ketua: mengubah teks bawaan di kode tidak berpengaruh
 * begitu panel pengaturan pernah disimpan, karena sejak itu nilainya dibaca dari
 * tabel settings. Yang paling terlihat adalah paragraf sambutan ketua.
 *
 * Pencarian dilakukan pada seluruh baris, bukan satu kunci tertentu, supaya
 * pengaturan lain yang kebetulan memuat nama sekolah ikut rapi — dan hanya baris
 * yang benar-benar memuat frasanya yang disentuh.
 */
return new class extends Migration
{
    private const LAMA = 'SMK Gotong Royong Telaga';

    private const BARU = 'SMK Gotong Royong';

    public function up(): void
    {
        $this->ganti(self::LAMA, self::BARU);
    }

    public function down(): void
    {
        $this->ganti(self::BARU, self::LAMA);
    }

    private function ganti(string $dari, string $ke): void
    {
        foreach (DB::table('settings')->get() as $baris) {
            if (! is_string($baris->value) || ! str_contains($baris->value, $dari)) {
                continue;
            }

            DB::table('settings')
                ->where('key', $baris->key)
                ->update(['value' => str_replace($dari, $ke, $baris->value)]);
        }
    }
};
