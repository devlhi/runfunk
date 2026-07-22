<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabar hasil verifikasi pembayaran: disetujui (beserta nomor BIB) atau
 * ditolak (beserta alasannya).
 */
class KabarPembayaran extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Registration $registration,
        private readonly bool $disetujui,
        private readonly ?string $alasan = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acara = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';
        $r = $this->registration;

        if ($this->disetujui) {
            return (new MailMessage)
                ->subject("Pembayaran diterima — nomor BIB kamu {$r->bib_number}")
                ->greeting("Halo, {$r->participant_name}")
                ->line("Pembayaran untuk pendaftaran {$r->registration_code} sudah kami verifikasi.")
                ->line("**Nomor BIB kamu: {$r->bib_number}** ({$r->category->distance_label})")
                ->line('Simpan e-tiketnya, nanti ditunjukkan saat mengambil race pack.')
                ->action('Lihat E-Tiket', url("/pendaftaran/{$r->id}"))
                ->salutation("Sampai jumpa di garis start,\nPanitia {$acara}");
        }

        return (new MailMessage)
            ->subject("Bukti pembayaran perlu diperbaiki — {$r->registration_code}")
            ->greeting("Halo, {$r->participant_name}")
            ->line('Maaf, bukti pembayaran yang kamu kirim belum bisa kami terima.')
            ->line('**Alasan: '.($this->alasan ?: 'Tidak disebutkan').'**')
            // Batas waktu disebut eksplisit supaya peserta tahu ini mendesak —
            // slot yang tidak diperbaiki akan dilepas otomatis.
            ->line('Silakan unggah ulang bukti yang benar sebelum batas waktu, agar slotmu tidak dilepas.')
            ->action('Unggah Ulang Bukti', url("/pendaftaran/{$r->id}"))
            ->salutation("Terima kasih,\nPanitia {$acara}");
    }
}
