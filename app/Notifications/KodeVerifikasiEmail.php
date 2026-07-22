<?php

namespace App\Notifications;

use App\Mail\VerifikasiEmail;
use Illuminate\Notifications\Notification;

/**
 * Kode verifikasi email untuk akun yang baru dibuat.
 *
 * Sengaja TIDAK diantrekan, berbeda dari kabar pembayaran dan pengumuman.
 * Kode ini memblokir: tanpa emailnya sampai, pendaftar tidak bisa berbuat apa-apa.
 * Di server yang tidak menjalankan `queue:work` — dan itu keadaan normal untuk
 * pemasangan Laragon — email yang diantrekan hanya menumpuk di tabel jobs, jadi
 * peserta terjebak di halaman verifikasi tanpa pernah menerima kodenya.
 */
class KodeVerifikasiEmail extends Notification
{

    public function __construct(
        private readonly string $kode,
        private readonly string $tautan,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): VerifikasiEmail
    {
        return (new VerifikasiEmail(
            nama: $notifiable->name,
            kode: $this->kode,
            tautan: $this->tautan,
        ))->to($notifiable->email);
    }
}
