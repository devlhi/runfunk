<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function requestForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [], ['email' => 'email']);

        $status = Password::sendResetLink($request->only('email'));

        // Jawaban selalu sama, supaya orang luar tidak bisa memakai halaman ini
        // untuk menebak-nebak email mana yang terdaftar.
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Tautan sudah dikirim baru-baru ini. Tunggu sebentar sebelum meminta lagi.',
            ]);
        }

        return back()->with(
            'success',
            'Kalau email tersebut terdaftar, kami sudah mengirimkan tautan untuk mengatur ulang kata sandi.'
        );
    }

    public function resetForm(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], [
            'email' => 'email',
            'password' => 'kata sandi baru',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang tidak berlaku atau sudah kedaluwarsa. Silakan minta tautan baru.',
            ]);
        }

        return redirect()->route('login')->with('success', 'Kata sandi berhasil diubah. Silakan masuk.');
    }
}
