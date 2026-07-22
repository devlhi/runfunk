<?php

namespace App\Http\Controllers;

use App\Services\EmailVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly EmailVerifier $verifier) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'city' => $user->city,
                'address' => $user->address,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email,'.$user->id],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['nullable', 'in:L,P'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $gantiEmail = $user->isStaff() === false
            && mb_strtolower($validated['email']) !== mb_strtolower($user->email);

        $user->update($validated);

        if (! $gantiEmail) {
            return back()->with('success', 'Profil diperbarui.');
        }

        // Alamat baru belum pernah dibuktikan miliknya. Tanpa mencabut status ini,
        // siapa pun bisa memverifikasi satu alamat lalu menggantinya ke alamat
        // orang lain dan tetap dianggap terverifikasi — seluruh gunanya verifikasi
        // hilang, dan kabar pembayaran bisa mendarat di kotak masuk orang asing.
        $user->forceFill(['email_verified_at' => null])->save();

        $this->verifier->terbitkan($user);

        return redirect()->route('verification.notice')->with(
            'success',
            'Email diganti. Kami kirim kode baru ke '.$user->email.' — verifikasi dulu untuk mengaktifkannya kembali.'
        );
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'current_password' => 'kata sandi saat ini',
            'password' => 'kata sandi baru',
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak cocok.']);
        }

        $request->user()->update(['password' => $validated['password']]);

        // Sesi lain diputus. Kalau kata sandi diganti justru karena akunnya diduga
        // dibobol, membiarkan sesi penyusup tetap hidup membuat penggantian ini
        // tidak ada gunanya.
        Auth::logoutOtherDevices($validated['password']);

        return back()->with('success', 'Kata sandi berhasil diubah. Perangkat lain yang masih masuk otomatis dikeluarkan.');
    }
}
