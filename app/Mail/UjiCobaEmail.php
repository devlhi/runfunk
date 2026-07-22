<?php

namespace App\Mail;

use App\Services\EmailBodyRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email percobaan dari halaman Pengaturan Acara.
 *
 * Isinya sengaja memuat host dan alamat pengirim yang dipakai: kalau developer
 * mengelola beberapa lingkungan, email yang masuk harus bisa dikenali datang
 * dari konfigurasi yang mana.
 */
class UjiCobaEmail extends Mailable
{
    public function __construct(
        public string $host,
        public string $pengirim,
        public string $waktu,
    ) {}

    private function data(): array
    {
        return [
            'host' => $this->host,
            'pengirim' => $this->pengirim,
            'waktu' => $this->waktu,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(EmailBodyRenderer::class)->subjek('uji-coba', $this->data())
        );
    }

    public function content(): Content
    {
        // Versi teks ikut dikirim: sebagian klien email dan filter spam menilai
        // pesan HTML-saja lebih mencurigakan, dan versi teks jadi cadangan kalau
        // gambar/gaya diblokir.
        return new Content(
            view: 'emails.dinamis',
            text: 'emails.teks.uji-coba',
            with: [
                'judul' => app(EmailBodyRenderer::class)->judul('uji-coba', $this->data()),
                'pratinjau' => 'Kalau email ini sampai, pengaturan SMTP sudah benar.',
                'isi' => app(EmailBodyRenderer::class)->render('uji-coba', $this->data()),
            ],
        );
    }
}
