<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jabatan yang tercetak di kartu panitia.
 *
 * Peran akun ("panitia" / "developer") menentukan apa yang boleh dibuka di
 * panel; yang dibutuhkan kartu justru keterangan tugas di lapangan — "Koordinator
 * Race Pack", "Tim Medis" — supaya orang tahu harus mencari siapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('card_title', 60)->nullable()->after('card_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('card_title');
        });
    }
};
