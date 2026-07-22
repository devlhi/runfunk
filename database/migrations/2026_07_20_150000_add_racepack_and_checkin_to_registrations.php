<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua momen paling ribut di lapangan: pengambilan race pack (H-2) dan
 * registrasi ulang pagi hari-H. Waktunya dicatat, bukan sekadar boolean,
 * supaya kalau ada peserta mengaku belum menerima race pack, panitia punya
 * jejak jam berapa dan oleh siapa ditandai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('racepack_at')->nullable()->after('verified_at');
            $table->foreignId('racepack_by')->nullable()->after('racepack_at')->constrained('users')->nullOnDelete();
            $table->timestamp('checkin_at')->nullable()->after('racepack_by');
            $table->foreignId('checkin_by')->nullable()->after('checkin_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('racepack_by');
            $table->dropConstrainedForeignId('checkin_by');
            $table->dropColumn(['racepack_at', 'checkin_at']);
        });
    }
};
