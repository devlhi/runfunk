<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly EmailVerifier $verifier) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'intendedCategory' => $request->query('kategori'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'in:L,P'],
            'birth_date' => ['required', 'date', 'before:today'],
            'city' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'name' => 'nama lengkap',
            'email' => 'email',
            'phone' => 'nomor WhatsApp',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            'city' => 'kota/kabupaten',
            'password' => 'kata sandi',
        ]);

        $user = User::create([
            ...$validated,
            'role' => User::ROLE_PESERTA,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        // Kode verifikasi dikirim sekarang, bukan saat halaman verifikasi dibuka:
        // peserta yang menutup tab masih menemukan emailnya sudah menunggu.
        $this->verifier->terbitkan($user);

        // Kategori pilihannya diingat lewat sesi supaya tidak hilang selama
        // peserta mampir ke halaman verifikasi lebih dulu.
        if ($request->input('kategori')) {
            $request->session()->put('kategori_tujuan', $request->input('kategori'));
        }

        return redirect()->route('verification.notice')->with(
            'success',
            'Akun berhasil dibuat. Satu langkah lagi — verifikasi emailmu.'
        );
    }
}
