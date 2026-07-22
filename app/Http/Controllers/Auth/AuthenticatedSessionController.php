<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'intendedCategory' => $request->query('kategori'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        if ($request->user()->isStaff()) {
            return redirect()->intended(route('panitia.dashboard'));
        }

        if ($request->filled('kategori')) {
            return redirect(route('registrations.create', ['kategori' => $request->input('kategori')]));
        }

        // Peserta yang sempat menyentuh URL panitia sebelum masuk tidak boleh
        // dilempar balik ke sana — ujungnya cuma halaman 403. Buang tujuan itu
        // dan antar dia ke dashboard-nya sendiri.
        if (str_starts_with((string) $request->session()->get('url.intended'), url('/panitia'))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Kamu sudah keluar. Sampai jumpa di garis start!');
    }
}
