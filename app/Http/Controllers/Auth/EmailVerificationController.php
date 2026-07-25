<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\EmailVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerifier $verifier) {}

    /** Halaman isi kode. */
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $kode = EmailVerificationCode::where('user_id', $request->user()->id)->first();

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $request->user()->email,
            'adaKode' => (bool) $kode,
            // Dipakai untuk menampilkan hitung mundur, bukan untuk menjaga apa pun —
            // kedaluwarsanya tetap diperiksa ulang di server saat kode dikirim.
            'kedaluwarsa' => $kode?->expires_at?->toIso8601String(),
            'berlakuMenit' => EmailVerificationCode::BERLAKU_MENIT,
        ]);
    }

    /** Kirim ulang kode. */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $hasil = $this->verifier->terbitkan($request->user());

        if ($hasil['terkirim']) {
            return back()->with('success', 'Kode baru sudah dikirim. Cek kotak masuk dan folder spam.');
        }

        // Keterangan teknisnya hanya untuk pengelola yang memang sedang menyetel
        // SMTP. Ke peserta cukup pesan umum: galat transport memuat nama host,
        // porta, dan sering nama pengguna SMTP.
        $pesan = $request->user()->isStaff() && filled($hasil['detail'])
            ? $hasil['pesan'].' ('.$hasil['detail'].')'
            : $hasil['pesan'];

        return back()->with('error', $pesan);
    }

    /** Periksa kode yang diketik peserta. */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'digits:6'],
        ], [
            'kode.required' => 'Ketik kode 6 angka dari email.',
            'kode.digits' => 'Kode verifikasi terdiri dari 6 angka.',
        ], ['kode' => 'kode verifikasi']);

        $hasil = $this->verifier->periksa($request->user(), $data['kode']);

        if (! $hasil['ok']) {
            return back()->withErrors(['kode' => $hasil['pesan']]);
        }

        return $this->lanjut($request)->with('success', $hasil['pesan']);
    }

    /**
     * Peserta yang mengklik "Daftar 5K" dari landing page dibawa lewat pembuatan
     * akun lalu verifikasi. Tanpa ini, setelah verifikasi mereka mendarat di
     * dashboard kosong dan harus mencari lagi kategori yang tadi dipilih.
     */
    private function lanjut(Request $request): RedirectResponse
    {
        $kategori = $request->session()->pull('kategori_tujuan');

        return $kategori
            ? redirect()->route('registrations.create', ['kategori' => $kategori])
            : redirect()->route('dashboard');
    }

    /**
     * Tombol sekali klik dari email.
     *
     * Tanda tangan URL sudah dijamin middleware 'signed'. Yang masih perlu
     * diperiksa di sini: tautan ini memang milik pengguna yang sedang masuk, dan
     * alamat emailnya belum berubah sejak tautannya diterbitkan.
     */
    public function verify(Request $request, User $user): RedirectResponse
    {
        if (! hash_equals((string) $request->user()->id, (string) $user->id)) {
            return redirect()->route('verification.notice')
                ->with('error', 'Tautan ini milik akun lain. Masuk dengan akun yang benar dulu.');
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->email))) {
            return redirect()->route('verification.notice')
                ->with('error', 'Alamat email sudah berubah sejak tautan dikirim. Minta kode baru.');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard')->with('success', 'Email ini sudah terverifikasi.');
        }

        $this->verifier->tandaiTerverifikasi($user);

        return $this->lanjut($request)
            ->with('success', 'Email berhasil diverifikasi. Selamat, akunmu sudah aktif!');
    }
}
