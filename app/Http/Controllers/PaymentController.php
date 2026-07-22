<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);

        if (! $registration->canUploadProof()) {
            return back()->with('error', 'Bukti pembayaran untuk pendaftaran ini sudah dikirim atau sudah lunas.');
        }

        $validated = $request->validate([
            'method' => ['required', 'in:transfer,qris,ewallet'],
            'sender_name' => ['required', 'string', 'max:120'],
            'sender_bank' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            // mimetypes memeriksa isi berkas (bukan sekadar ekstensi), jadi skrip yang
            // disamarkan sebagai .jpg tetap ditolak.
            'proof' => [
                'required', 'file', 'max:4096',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
        ], [
            'proof.mimetypes' => 'Bukti pembayaran harus berupa gambar (JPG, PNG, WEBP) atau PDF yang sah.',
        ], [
            'method' => 'metode pembayaran',
            'sender_name' => 'nama pengirim',
            'sender_bank' => 'bank/e-wallet pengirim',
            'paid_at' => 'tanggal transfer',
            'proof' => 'bukti pembayaran',
        ]);

        $path = $request->file('proof')->store("bukti-bayar/{$registration->id}", 'local');

        Payment::create([
            'registration_id' => $registration->id,
            'method' => $validated['method'],
            'amount' => $registration->amount,
            'sender_name' => $validated['sender_name'],
            'sender_bank' => $validated['sender_bank'] ?? null,
            'proof_path' => $path,
            'paid_at' => $validated['paid_at'],
            'status' => Payment::STATUS_PENDING,
        ]);

        $registration->update([
            'status' => Registration::STATUS_WAITING_VERIFICATION,
            'panitia_note' => null,
        ]);

        return redirect()
            ->route('registrations.show', $registration)
            ->with('success', 'Bukti pembayaran terkirim. Panitia akan memverifikasi maksimal 1x24 jam.');
    }
}
