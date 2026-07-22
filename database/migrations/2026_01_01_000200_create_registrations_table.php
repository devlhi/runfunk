<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('registration_code', 20)->unique();   // GFR-5K-0001
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('race_category_id')->constrained();

            // Data peserta (bisa berbeda dari data akun, mis. mendaftarkan anggota keluarga)
            $table->string('participant_name');
            $table->string('participant_email');
            $table->string('participant_phone', 30);
            $table->enum('gender', ['L', 'P']);
            $table->date('birth_date');
            $table->string('city');
            $table->text('address')->nullable();
            $table->string('jersey_size', 6);                    // XS..XXXL
            $table->string('blood_type', 3)->nullable();
            $table->string('community')->nullable();
            $table->string('emergency_name');
            $table->string('emergency_phone', 30);

            $table->unsignedInteger('amount');
            $table->string('bib_number', 10)->nullable()->unique();
            $table->enum('status', [
                'pending_payment',      // belum bayar
                'waiting_verification', // bukti bayar diunggah, menunggu panitia
                'confirmed',            // lunas & terverifikasi
                'rejected',             // bukti bayar ditolak
                'cancelled',            // dibatalkan peserta/panitia
            ])->default('pending_payment')->index();

            $table->text('panitia_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();         // batas waktu bayar
            $table->timestamps();

            $table->index(['race_category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
