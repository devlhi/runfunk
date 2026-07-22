<?php

use App\Http\Middleware\EnsureDeveloper;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsurePanitia;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Mengikat sesi ke hash kata sandi pemiliknya. Tanpa ini,
            // Auth::logoutOtherDevices() hanya mengganti kata sandi tanpa
            // benar-benar memutus sesi lain — penyusup yang sudah masuk tetap
            // bertahan meski korbannya sudah mengganti kata sandi.
            AuthenticateSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'panitia' => EnsurePanitia::class,
            'developer' => EnsureDeveloper::class,
            'email.terverifikasi' => EnsureEmailVerified::class,
        ]);

        // Panitia yang sudah masuk lalu membuka halaman tamu diarahkan ke panelnya
        // sendiri, bukan ke dashboard peserta.
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->isPanitia() ? '/panitia' : '/dashboard'
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
