<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga halaman yang hanya boleh disentuh developer: kelola akun pengelola
 * dan pengaturan acara. Panitia biasa tetap ditolak di sini.
 */
class EnsureDeveloper
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isDeveloper()) {
            abort(403, 'Halaman ini khusus untuk developer.');
        }

        return $next($request);
    }
}
