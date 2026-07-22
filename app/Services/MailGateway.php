<?php

namespace App\Services;

use App\Mail\UjiCobaEmail;
use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kredensial SMTP yang bisa diubah developer lewat antarmuka.
 *
 * Sama seperti gateway WhatsApp, sumbernya tabel settings dan bukan .env, supaya
 * panitia tidak perlu menyentuh berkas di server hanya untuk mengganti akun
 * pengirim. Selama sakelarnya mati, mailer bawaan Laravel dari .env yang dipakai —
 * jadi lingkungan pengembangan tetap menulis email ke log seperti biasa.
 */
class MailGateway
{
    public function aktif(): bool
    {
        return Setting::ambil('mail_enabled') === '1'
            && filled(Setting::ambil('mail_host'))
            && filled(Setting::ambil('mail_from_address'));
    }

    /**
     * Menimpa konfigurasi mailer aktif dengan nilai dari tabel settings.
     *
     * Dipanggil sekali di awal daur hidup aplikasi, jadi semua notifikasi yang
     * sudah ada ikut terkirim lewat akun ini tanpa perlu diubah satu per satu.
     */
    public function terapkan(): void
    {
        if (! $this->aktif()) {
            return;
        }

        $skema = Setting::ambil('mail_scheme');

        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => Setting::ambil('mail_host'),
            'mail.mailers.smtp.port' => (int) Setting::ambil('mail_port'),
            'mail.mailers.smtp.scheme' => in_array($skema, Setting::SKEMA_MAIL, true) ? $skema : 'smtp',
            'mail.mailers.smtp.username' => Setting::ambil('mail_username') ?: null,
            'mail.mailers.smtp.password' => Setting::ambil('mail_password') ?: null,
            'mail.from.address' => Setting::ambil('mail_from_address'),
            'mail.from.name' => Setting::ambil('mail_from_name'),
        ]);

        // Mailer yang terlanjur dibuat masih memegang transport lama, jadi
        // cache-nya dibuang supaya dibangun ulang dengan kredensial baru.
        Mail::purge('smtp');
    }

    /**
     * Kirim satu email percobaan. Tidak pernah melempar exception — kegagalan
     * SMTP dikembalikan sebagai pesan supaya developer bisa membacanya langsung
     * di halaman pengaturan, bukan sebagai layar galat 500.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function ujiKirim(string $tujuan): array
    {
        if (! $this->aktif()) {
            return [
                'ok' => false,
                'pesan' => 'Gateway email belum aktif. Isi host SMTP dan email pengirim, '
                    .'nyalakan sakelarnya, lalu simpan dulu sebelum menguji.',
            ];
        }

        $this->terapkan();

        try {
            Mail::to($tujuan)->send(new UjiCobaEmail(
                host: Setting::ambil('mail_host').':'.Setting::ambil('mail_port'),
                pengirim: (string) Setting::ambil('mail_from_address'),
                waktu: now()->translatedFormat('d M Y, H:i'),
            ));

            return ['ok' => true, 'pesan' => "Email percobaan terkirim ke {$tujuan}. Cek kotak masuk dan folder spam."];
        } catch (\Throwable $e) {
            // Pesan aslinya ikut ditampilkan: penyebab gagal SMTP hampir selalu
            // spesifik (kredensial salah, port diblokir, sertifikat kedaluwarsa)
            // dan tidak bisa ditebak dari pesan umum.
            Log::warning('Uji kirim email gagal', ['tujuan' => $tujuan, 'error' => $e->getMessage()]);

            return ['ok' => false, 'pesan' => 'Gagal mengirim: '.$e->getMessage()];
        }
    }
}
