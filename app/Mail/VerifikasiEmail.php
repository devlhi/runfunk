<?php

namespace App\Mail;

use App\Models\EmailVerificationCode;
use App\Services\EmailBodyRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email verifikasi akun: berisi kode ketik sekaligus tombol sekali klik.
 *
 * Subjek dan badannya diambil dari template yang bisa disunting developer;
 * kerangka, tanda tangan, dan kakinya tetap dari berkas Blade.
 */
class VerifikasiEmail extends Mailable
{
    public function __construct(
        public string $nama,
        public string $kode,
        public string $tautan,
    ) {}

    private function data(): array
    {
        return [
            'nama' => $this->nama,
            'kode' => $this->kode,
            'tautan' => $this->tautan,
            'berlaku' => (string) EmailVerificationCode::BERLAKU_MENIT,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(EmailBodyRenderer::class)->subjek('verifikasi', $this->data())
        );
    }

    public function content(): Content
    {
        $renderer = app(EmailBodyRenderer::class);

        return new Content(
            view: 'emails.dinamis',
            text: 'emails.teks.verifikasi',
            with: [
                'judul' => $renderer->judul('verifikasi', $this->data()),
                'pratinjau' => "Kode verifikasi kamu {$this->kode}.",
                'isi' => $renderer->render('verifikasi', $this->data()),
                'berlaku' => EmailVerificationCode::BERLAKU_MENIT,
            ],
        );
    }
}
