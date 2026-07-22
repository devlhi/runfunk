<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cegah situs disematkan di iframe situs lain (clickjacking pada tombol
        // "Setujui Pembayaran" panitia).
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Jangan biarkan browser menebak-nebak tipe berkas bukti bayar yang diunggah.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        $response->headers->set('Content-Security-Policy', $this->csp());

        return $response;
    }

    /**
     * Lapis pertahanan terakhir kalau ada lubang XSS yang lolos.
     *
     * Situs ini menampilkan dua hal yang diketik orang: komentar berita dan isi
     * template email. Keduanya sudah disaring di hulu, tapi CSP membuat skrip
     * asing tetap tidak bisa jalan seandainya satu penyaring meleset.
     *
     * 'unsafe-inline' pada style tidak bisa dihindari: halaman ini memakai
     * banyak atribut style= sebaris. Yang penting script-src tetap ketat —
     * di situlah letak bahayanya, bukan di gaya.
     */
    private function csp(): string
    {
        $aturan = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        // Vite dev server menyuntikkan skrip HMR dan menyambung lewat websocket.
        // Tanpa kelonggaran ini, `npm run dev` mati total di balik CSP.
        if (app()->environment('local') && file_exists(public_path('hot'))) {
            $vite = rtrim((string) file_get_contents(public_path('hot')), "\n");

            $aturan[1] = "script-src 'self' 'unsafe-inline' {$vite}";
            $aturan[2] = "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com {$vite}";
            $aturan[5] = "connect-src 'self' {$vite} ".str_replace('http', 'ws', $vite);
        }

        return implode('; ', $aturan);
    }
}
