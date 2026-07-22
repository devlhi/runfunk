<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil lomba disimpan langsung di baris pendaftaran, bukan tabel terpisah:
 * satu peserta hanya punya satu hasil per pendaftaran, jadi relasi terpisah
 * hanya menambah join tanpa menambah kemampuan.
 *
 * Waktu disimpan dalam DETIK, bukan teks "01:02:03". Angka bisa diurutkan dan
 * dibandingkan langsung oleh basis data — format tampilannya urusan tampilan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedInteger('finish_seconds')->nullable()->after('transferred_at');
            $table->unsignedInteger('rank_overall')->nullable()->after('finish_seconds');
            $table->unsignedInteger('rank_gender')->nullable()->after('rank_overall');
            $table->timestamp('finished_at')->nullable()->after('rank_gender');

            // Dipakai untuk mengurutkan papan hasil per kategori.
            $table->index(['race_category_id', 'finish_seconds']);
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['race_category_id', 'finish_seconds']);
            $table->dropColumn(['finish_seconds', 'rank_overall', 'rank_gender', 'finished_at']);
        });
    }
};
