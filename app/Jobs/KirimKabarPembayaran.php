<?php

namespace App\Jobs;

use App\Models\Registration;
use App\Models\Setting;
use App\Notifications\KabarPembayaran;
use App\Services\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Mengabari peserta hasil verifikasi pembayarannya lewat email dan WhatsApp.
 *
 * Dijalankan lewat antrean supaya panitia tidak menunggu gateway saat menekan
 * tombol setujui — dan supaya gateway yang sedang mati tidak membuat proses
 * verifikasinya ikut gagal.
 */
class KirimKabarPembayaran implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly int $registrationId,
        private readonly bool $disetujui,
        private readonly ?string $alasan = null,
    ) {}

    public function handle(WhatsAppGateway $wa): void
    {
        $registrasi = Registration::with(['user', 'category'])->find($this->registrationId);

        if (! $registrasi) {
            return;
        }

        $acara = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';

        if ($registrasi->user) {
            try {
                $registrasi->user->notify(
                    new KabarPembayaran($registrasi, $this->disetujui, $this->alasan)
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal kirim email kabar pembayaran', [
                    'registrasi' => $registrasi->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $wa->kirim($registrasi->participant_phone, $this->pesanWa($registrasi, $acara));
    }

    private function pesanWa(Registration $r, string $acara): string
    {
        if ($this->disetujui) {
            return "*Pembayaran diterima!* ✅\n\n"
                ."Halo {$r->participant_name}, pendaftaran {$r->registration_code} sudah lunas.\n\n"
                ."*Nomor BIB kamu: {$r->bib_number}*\n"
                ."Kategori: {$r->category->distance_label}\n\n"
                ."Simpan e-tiketnya untuk ditunjukkan saat ambil race pack.\n\n"
                ."— Panitia {$acara}";
        }

        return "*Bukti pembayaran perlu diperbaiki* ⚠️\n\n"
            ."Halo {$r->participant_name}, bukti bayar untuk {$r->registration_code} belum bisa kami terima.\n\n"
            .'*Alasan: '.($this->alasan ?: 'Tidak disebutkan')."*\n\n"
            .'Mohon unggah ulang bukti yang benar lewat dashboard sebelum batas waktu, '
            ."supaya slotmu tidak dilepas.\n\n"
            ."— Panitia {$acara}";
    }
}
