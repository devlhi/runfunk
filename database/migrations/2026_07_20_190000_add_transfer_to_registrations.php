<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pengalihan slot ke orang lain, sesuai yang dijanjikan di FAQ.
 *
 * Nama pemilik lama disimpan sebagai teks, bukan relasi: yang perlu diketahui
 * panitia adalah "slot ini dulunya atas nama siapa", dan itu harus tetap
 * terbaca walau akun lamanya kelak dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('transferred_from', 120)->nullable()->after('checkin_by');
            $table->timestamp('transferred_at')->nullable()->after('transferred_from');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['transferred_from', 'transferred_at']);
        });
    }
};
