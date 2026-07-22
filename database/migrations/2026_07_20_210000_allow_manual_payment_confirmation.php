<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mendukung konfirmasi pembayaran tanpa bukti unggahan.
 *
 * Tidak semua peserta membayar lewat transfer lalu mengunggah buktinya —
 * sebagian menyerahkan tunai langsung ke panitia, atau panitia melihat dananya
 * masuk di mutasi rekening tanpa peserta pernah membuka situs. Untuk kasus itu
 * bukti tidak ada, jadi kolomnya harus boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY method ENUM('transfer','qris','ewallet','tunai','manual') NOT NULL");
        DB::statement('ALTER TABLE payments MODIFY proof_path VARCHAR(255) NULL');

        Schema::table('payments', function (Blueprint $table) {
            // Catatan panitia soal bagaimana pembayarannya diverifikasi —
            // penting sebagai jejak untuk pembayaran yang tanpa bukti digital.
            $table->string('confirm_note', 255)->nullable()->after('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('confirm_note');
        });

        DB::table('payments')->whereIn('method', ['tunai', 'manual'])->update(['method' => 'transfer']);
        DB::table('payments')->whereNull('proof_path')->update(['proof_path' => '']);

        DB::statement("ALTER TABLE payments MODIFY method ENUM('transfer','qris','ewallet') NOT NULL");
        DB::statement('ALTER TABLE payments MODIFY proof_path VARCHAR(255) NOT NULL');
    }
};
