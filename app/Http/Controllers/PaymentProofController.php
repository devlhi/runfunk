<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /**
     * Bukti pembayaran memuat data rekening pribadi, jadi tidak boleh diletakkan di
     * folder publik. Berkas disimpan di disk privat dan hanya dialirkan ke pemilik
     * pendaftaran atau panitia.
     */
    public function show(Request $request, Payment $payment): StreamedResponse
    {
        $user = $request->user();
        $isOwner = $payment->registration->user_id === $user->id;

        abort_unless($isOwner || $user->isStaff(), 403);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response(
            $payment->proof_path,
            null,
            ['Content-Disposition' => 'inline']
        );
    }
}
