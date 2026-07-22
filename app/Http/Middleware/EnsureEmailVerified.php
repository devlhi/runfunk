<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan pengambilan slot lomba sampai alamat emailnya terbukti benar.
 *
 * Dipasang di pendaftaran lomba saja, bukan di seluruh area login: peserta tetap
 * boleh masuk, melihat dashboard, dan mengurus profilnya. Yang dijaga adalah
 * slot — karena slot yang diambil akun dengan email salah ketik tidak akan
 * pernah bisa dikabari soal pembayaran maupun nomor BIB-nya.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Panitia & developer dibuatkan akunnya oleh developer, bukan mendaftar
        // sendiri, jadi tidak ada email verifikasi yang pernah dikirim ke mereka.
        if (! $user || $user->isStaff() || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return redirect()->route('verification.notice')->with(
            'error',
            'Verifikasi email dulu sebelum mengambil slot lomba — supaya kabar pembayaran dan nomor BIB pasti sampai.'
        );
    }
}
