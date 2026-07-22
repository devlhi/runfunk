<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pas foto panitia untuk kartu tanda pengenal.
 *
 * Boleh kosong: sebagian panitia tidak sempat menyiapkan berkas fotonya, dan
 * kartunya tetap harus bisa dicetak. Yang tanpa foto keluar dengan bingkai
 * kosong bertuliskan "tempel pas foto", supaya bisa ditempel manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('card_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
