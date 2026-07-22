<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Services\EmailBodyRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menyunting dan melihat desain email tanpa mengirim apa pun.
 *
 * Pratinjaunya memakai data contoh, bukan data peserta sungguhan: halaman ini
 * dibuka untuk memeriksa tata letak, dan tidak ada alasan menampilkan alamat
 * email atau nomor BIB orang lain hanya demi melihat rupanya.
 */
class EmailPreviewController extends Controller
{
    public function __construct(private readonly EmailBodyRenderer $renderer) {}

    /** Data contoh per template, dipakai pratinjau. */
    private function contoh(string $key): array
    {
        return match ($key) {
            'verifikasi' => [
                'nama' => 'Rian Pelari',
                'kode' => '482915',
                'tautan' => url('/verifikasi-email/contoh-tautan-bertanda-tangan'),
                'berlaku' => '60',
            ],
            'uji-coba' => [
                'host' => (Setting::ambil('mail_host') ?: 'smtp.contoh.id').':'.(Setting::ambil('mail_port') ?: '587'),
                'pengirim' => Setting::ambil('mail_from_address') ?: 'panitia@gongfunrun.id',
                'waktu' => now()->translatedFormat('d M Y, H:i'),
            ],
            default => [],
        };
    }

    public function index(): Response
    {
        return Inertia::render('Panitia/EmailPreview', [
            'templates' => collect(EmailTemplate::definisi())
                ->map(fn (array $meta, string $kunci) => EmailTemplate::ambil($kunci))
                ->values(),
            'pengirim' => [
                'alamat' => Setting::ambil('mail_from_address') ?: config('mail.from.address'),
                'nama' => Setting::ambil('mail_from_name') ?: config('mail.from.name'),
                'aktif' => Setting::ambil('mail_enabled') === '1',
            ],
        ]);
    }

    /** Render satu template tersimpan sebagai HTML mentah, untuk di dalam iframe. */
    public function show(string $template): HttpResponse
    {
        abort_unless(EmailTemplate::adaKunci($template), 404);

        $data = $this->contoh($template);

        return $this->halaman(view('emails.dinamis', [
            'judul' => $this->renderer->judul($template, $data),
            'pratinjau' => '',
            'isi' => $this->renderer->render($template, $data),
        ])->render());
    }

    /**
     * Pratinjau isi yang MASIH DIKETIK, belum disimpan.
     *
     * Dikirim lewat POST karena isinya bisa panjang dan tidak boleh masuk ke URL,
     * lalu dikembalikan sebagai HTML untuk ditanam di iframe bersandbox.
     */
    public function draft(Request $request, string $template): HttpResponse
    {
        abort_unless(EmailTemplate::adaKunci($template), 404);

        $data = $request->validate([
            'isi' => ['present', 'string', 'max:20000'],
        ]);

        $contoh = $this->contoh($template);

        return $this->halaman(view('emails.dinamis', [
            'judul' => $this->renderer->judul($template, $contoh),
            'pratinjau' => '',
            'isi' => $this->renderer->susun($data['isi'], $contoh),
        ])->render());
    }

    public function update(Request $request, string $template): RedirectResponse
    {
        abort_unless(EmailTemplate::adaKunci($template), 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'isi' => ['required', 'string', 'max:20000'],
        ], [], ['subject' => 'judul email', 'isi' => 'isi email']);

        EmailTemplate::updateOrCreate(
            ['key' => $template],
            [
                'subject' => $data['subject'],
                // Disaring sebelum disimpan, bukan hanya saat ditampilkan: kalau
                // hanya disaring saat render, isi berbahaya tetap mengendap di
                // basis data dan ikut terbawa ke mana pun ia dipakai nanti.
                'body_html' => $this->renderer->bersihkan($data['isi']),
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Template email disimpan. Email berikutnya memakai isi ini.');
    }

    /** Kembalikan ke isi bawaan. */
    public function reset(string $template): RedirectResponse
    {
        abort_unless(EmailTemplate::adaKunci($template), 404);

        EmailTemplate::where('key', $template)->delete();

        return back()->with('success', 'Template dikembalikan ke isi bawaan.');
    }

    private function halaman(string $html): HttpResponse
    {
        // Dikirim sebagai dokumen terpisah di dalam iframe, bukan disisipkan ke
        // halaman panel: gaya email memakai tabel dan warna latar sendiri yang
        // akan bentrok kalau digabung ke DOM panel.
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
