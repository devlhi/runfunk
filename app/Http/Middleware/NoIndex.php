<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melarang mesin pencari mengindeks halaman yang berisi data orang.
 *
 * Dipasang sebagai tajuk HTTP, bukan tag <meta>, karena dua alasan:
 *
 * 1. Berlaku juga untuk jawaban yang bukan HTML — bukti bayar dan pas foto
 *    disajikan sebagai berkas gambar, dan tag <meta> tidak bisa disisipkan
 *    ke sana.
 * 2. Tidak bisa lupa dipasang. Halaman baru di grup rute ini otomatis ikut
 *    terlindungi, tanpa harus ingat menambahkan apa pun di templatnya.
 *
 * robots.txt saja tidak cukup: berkas itu mencegah perayapan, bukan
 * pengindeksan. Alamat yang pernah dibagikan orang tetap bisa muncul di hasil
 * pencarian meski isinya tidak pernah dirayapi.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
