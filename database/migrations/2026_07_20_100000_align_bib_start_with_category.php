<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nomor BIB dibuat diawali angka kategorinya supaya panitia bisa tahu jarak
 * seorang pelari hanya dari nomor di dadanya: 5K -> 5xxx, 10K -> 10xxx.
 */
return new class extends Migration
{
    private array $awal = [
        '5k' => 5001,
        '10k' => 10001,
    ];

    public function up(): void
    {
        foreach ($this->awal as $slug => $start) {
            DB::table('race_categories')->where('slug', $slug)->update(['bib_start' => $start]);
        }
    }

    public function down(): void
    {
        foreach (['5k' => 1001, '10k' => 5001] as $slug => $start) {
            DB::table('race_categories')->where('slug', $slug)->update(['bib_start' => $start]);
        }
    }
};
