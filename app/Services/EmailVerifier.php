<?php

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\KodeVerifikasiEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Penerbitan dan pemeriksaan kode verifikasi email.
 *
 * Dua jalur disediakan sekaligus karena keduanya gagal di keadaan yang berbeda:
 * tombol tanda tangan gagal kalau peserta membuka email di ponsel tapi akunnya
 * terbuka di laptop, sedangkan kode ketik gagal kalau angkanya salah salin.
 */
class EmailVerifier
{
    /**
     * Terbitkan kode baru dan kirimkan emailnya.
     *
     * Kode lama sengaja ditimpa, bukan ditambah: meminta kode baru harus membuat
     * kode sebelumnya tidak berlaku, supaya tidak ada dua kode sah beredar.
     *
     * Kegagalan kirim tidak dilempar sebagai galat. Barisnya tetap dibuat supaya
     * peserta bisa menekan "Kirim ulang" setelah SMTP-nya dibetulkan, tanpa harus
     * mendaftar ulang dari awal.
     *
     * @return array{kode: string, terkirim: bool, pesan: string}
     */
    public function terbitkan(User $user): array
    {
        $kode = (string) random_int(100000, 999999);

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($kode),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(EmailVerificationCode::BERLAKU_MENIT),
            ]
        );

        try {
            $user->notify(new KodeVerifikasiEmail($kode, $this->tautanBertandaTangan($user)));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim kode verifikasi', ['user' => $user->id, 'error' => $e->getMessage()]);

            return [
                'kode' => $kode,
                'terkirim' => false,
                'pesan' => 'Kode dibuat, tapi emailnya gagal terkirim: '.$e->getMessage(),
            ];
        }

        return ['kode' => $kode, 'terkirim' => true, 'pesan' => 'Kode verifikasi dikirim ke '.$user->email];
    }

    /**
     * Tautan sekali klik. Tanda tangannya mengunci id pengguna dan cap waktu, jadi
     * tautannya tidak bisa dikarang sendiri maupun dipakai lagi setelah lewat masa.
     */
    public function tautanBertandaTangan(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(EmailVerificationCode::BERLAKU_MENIT),
            ['user' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    /**
     * Periksa kode ketikan.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function periksa(User $user, string $kode): array
    {
        if ($user->hasVerifiedEmail()) {
            return ['ok' => true, 'pesan' => 'Email ini sudah terverifikasi.'];
        }

        $catatan = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $catatan) {
            return ['ok' => false, 'pesan' => 'Belum ada kode aktif. Minta kode baru dulu.'];
        }

        if ($catatan->kedaluwarsa()) {
            $catatan->delete();

            return ['ok' => false, 'pesan' => 'Kodenya sudah kedaluwarsa. Minta kode baru.'];
        }

        if ($catatan->habisPercobaan()) {
            $catatan->delete();

            return ['ok' => false, 'pesan' => 'Terlalu banyak percobaan salah. Kode dihanguskan — minta kode baru.'];
        }

        if (! Hash::check($kode, $catatan->code_hash)) {
            $catatan->increment('attempts');
            $sisa = $catatan->fresh()->sisaPercobaan();

            return [
                'ok' => false,
                'pesan' => $sisa > 0
                    ? "Kode salah. Sisa {$sisa} percobaan sebelum kode dihanguskan."
                    : 'Kode salah dan percobaan habis. Minta kode baru.',
            ];
        }

        $this->tandaiTerverifikasi($user);

        return ['ok' => true, 'pesan' => 'Email berhasil diverifikasi. Selamat, akunmu sudah aktif!'];
    }

    /** Kode dihapus setelah dipakai supaya tidak bisa dipakai kedua kali. */
    public function tandaiTerverifikasi(User $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();

        EmailVerificationCode::where('user_id', $user->id)->delete();
    }
}
