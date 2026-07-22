<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai kapan pengumuman pernah disiarkan, supaya panitia tidak mengirim
 * pesan yang sama dua kali ke ribuan peserta tanpa sadar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('broadcast_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('broadcast_at');
        });
    }
};
