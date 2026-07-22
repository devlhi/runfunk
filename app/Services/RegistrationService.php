<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    /**
     * Buat pendaftaran baru sambil mengunci kuota kategori supaya tidak over-booking
     * ketika dua peserta menekan tombol daftar bersamaan.
     */
    public function create(User $user, RaceCategory $category, array $data): Registration
    {
        return DB::transaction(function () use ($user, $category, $data) {
            $category = RaceCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (! $category->is_active) {
                throw ValidationException::withMessages([
                    'race_category_id' => 'Kategori ini sedang tidak dibuka.',
                ]);
            }

            if ($category->isSoldOut()) {
                throw ValidationException::withMessages([
                    'race_category_id' => "Kuota kategori {$category->distance_label} sudah habis.",
                ]);
            }

            $duplicate = Registration::where('user_id', $user->id)
                ->where('race_category_id', $category->id)
                ->active()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'race_category_id' => "Kamu sudah punya pendaftaran aktif di kategori {$category->distance_label}.",
                ]);
            }

            return Registration::create([
                'registration_code' => $this->nextRegistrationCode($category),
                'user_id' => $user->id,
                'race_category_id' => $category->id,
                'participant_name' => $data['participant_name'],
                'participant_email' => $data['participant_email'],
                'participant_phone' => $data['participant_phone'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'],
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'jersey_size' => $data['jersey_size'],
                'blood_type' => $data['blood_type'] ?? null,
                'community' => $data['community'] ?? null,
                'emergency_name' => $data['emergency_name'],
                'emergency_phone' => $data['emergency_phone'],
                'amount' => $category->price,
                'status' => Registration::STATUS_PENDING_PAYMENT,
                'expires_at' => now()->addHours($this->batasWaktuBayar()),
            ]);
        });
    }

    /**
     * Setujui pembayaran: tandai lunas dan terbitkan nomor BIB.
     */
    public function approvePayment(Payment $payment, User $panitia): Registration
    {
        return DB::transaction(function () use ($payment, $panitia) {
            $registration = Registration::whereKey($payment->registration_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->update([
                'status' => Payment::STATUS_APPROVED,
                'reject_reason' => null,
                'reviewed_by' => $panitia->id,
                'reviewed_at' => now(),
            ]);

            $registration->update([
                'status' => Registration::STATUS_CONFIRMED,
                'bib_number' => $registration->bib_number ?: $this->nextBibNumber($registration->race_category_id),
                'verified_by' => $panitia->id,
                'verified_at' => now(),
                'panitia_note' => null,
                'expires_at' => null,
            ]);

            return $registration->fresh();
        });
    }

    public function rejectPayment(Payment $payment, User $panitia, string $reason): Registration
    {
        return DB::transaction(function () use ($payment, $panitia, $reason) {
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'reject_reason' => $reason,
                'reviewed_by' => $panitia->id,
                'reviewed_at' => now(),
            ]);

            $registration = $payment->registration;
            $registration->update([
                'status' => Registration::STATUS_REJECTED,
                'panitia_note' => $reason,
                'expires_at' => now()->addHours($this->batasWaktuBayar()),
            ]);

            return $registration->fresh();
        });
    }

    /**
     * Batas waktu penyelesaian pembayaran, dalam jam.
     *
     * Dibaca dari pengaturan lebih dulu, baru jatuh ke config. Sebelumnya hanya
     * membaca config, sehingga kolom "Batas Waktu Bayar" di halaman Pengaturan
     * Acara bisa diubah developer tanpa efek apa pun — slot tetap dilepas
     * mengikuti nilai lama, dan tidak ada tanda apa-apa bahwa itu terjadi.
     */
    private function batasWaktuBayar(): int
    {
        $jam = (int) (Setting::ambil('payment_deadline_hours') ?: 0);

        return $jam > 0 ? $jam : (int) config('funrun.payment_deadline_hours');
    }

    /**
     * GFR-5K-0001 — nomor urut per kategori.
     */
    protected function nextRegistrationCode(RaceCategory $category): string
    {
        $sequence = Registration::where('race_category_id', $category->id)->count() + 1;
        $prefix = 'GFR-'.strtoupper($category->distance_label);

        do {
            $code = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Registration::where('registration_code', $code)->exists());

        return $code;
    }

    /**
     * BIB melanjutkan nomor terakhir yang terbit di kategori tersebut.
     */
    protected function nextBibNumber(int $categoryId): string
    {
        $category = RaceCategory::findOrFail($categoryId);

        $highest = Registration::where('race_category_id', $categoryId)
            ->whereNotNull('bib_number')
            ->max(DB::raw('CAST(bib_number AS UNSIGNED)'));

        $next = $highest ? ((int) $highest) + 1 : $category->bib_start;

        while (Registration::where('bib_number', (string) $next)->exists()) {
            $next++;
        }

        return (string) $next;
    }
}
