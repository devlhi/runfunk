<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Jobs\KirimKabarPembayaran;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\RegistrationService;
use App\Services\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentVerificationController extends Controller
{
    public function __construct(private readonly RegistrationService $service) {}

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        if ($error = $this->blockedReason($payment)) {
            return back()->with('error', $error);
        }

        $registration = $this->service->approvePayment($payment, $request->user());

        // Diantrekan setelah verifikasi berhasil, jadi gateway yang bermasalah
        // tidak pernah membatalkan penerbitan BIB yang sudah sah.
        KirimKabarPembayaran::dispatch($registration->id, true);

        return back()->with(
            'success',
            "Pembayaran {$registration->registration_code} disetujui. Nomor BIB {$registration->bib_number} diterbitkan, "
            .'dan peserta otomatis dikabari.'
        );
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Tulis alasan penolakan agar peserta tahu apa yang harus diperbaiki.',
        ]);

        if ($error = $this->blockedReason($payment)) {
            return back()->with('error', $error);
        }

        $registration = $this->service->rejectPayment($payment, $request->user(), $validated['reason']);

        KirimKabarPembayaran::dispatch($registration->id, false, $validated['reason']);

        return back()->with(
            'success',
            "Bukti bayar {$registration->registration_code} ditolak. Peserta otomatis dikabari beserta alasannya."
        );
    }

    /**
     * Konfirmasi pembayaran yang TIDAK punya bukti unggahan — peserta membayar
     * tunai ke panitia, atau dananya terlihat di mutasi rekening tanpa peserta
     * pernah mengunggah apa pun.
     *
     * Karena tidak ada bukti digital yang bisa diperiksa ulang, catatan panitia
     * diwajibkan dan seluruh kejadiannya dilaporkan ke WhatsApp admin sebagai
     * jejak — ini satu-satunya jalur di mana uang diakui masuk tanpa berkas.
     */
    public function manual(Request $request, Registration $registration, WhatsAppGateway $wa): RedirectResponse
    {
        $data = $request->validate([
            'metode' => ['required', Rule::in(Payment::METODE_MANUAL)],
            'catatan' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'catatan.required' => 'Tulis bagaimana pembayaran ini diverifikasi, sebagai jejak untuk panitia lain.',
            'catatan.min' => 'Catatannya terlalu singkat — tulis yang jelas.',
        ], ['metode' => 'metode pembayaran', 'catatan' => 'catatan verifikasi']);

        if ($registration->status === Registration::STATUS_CONFIRMED) {
            return back()->with('error', 'Pendaftaran ini sudah berstatus lunas.');
        }

        if ($registration->status === Registration::STATUS_CANCELLED) {
            return back()->with('error', 'Pendaftaran ini sudah dibatalkan.');
        }

        $panitia = $request->user();

        $payment = $registration->payments()->create([
            'method' => $data['metode'],
            'amount' => $registration->amount,
            'sender_name' => $registration->participant_name,
            'proof_path' => null,
            'paid_at' => today(),
            'status' => Payment::STATUS_PENDING,
            'confirm_note' => $data['catatan'],
        ]);

        $registration = $this->service->approvePayment($payment, $panitia);

        KirimKabarPembayaran::dispatch($registration->id, true);
        $this->laporkanKeAdmin($wa, $registration, $panitia->name, $data['metode'], $data['catatan']);

        return back()->with(
            'success',
            "Pembayaran {$registration->registration_code} dikonfirmasi manual. "
            ."Nomor BIB {$registration->bib_number} diterbitkan dan laporan dikirim ke WhatsApp admin."
        );
    }

    /**
     * Laporan ke nomor admin. Sengaja tetap dikirim walau gateway mati —
     * kegagalannya dicatat di log, tidak membatalkan konfirmasi yang sudah sah.
     */
    private function laporkanKeAdmin(
        WhatsAppGateway $wa,
        Registration $registration,
        string $panitia,
        string $metode,
        string $catatan,
    ): void {
        $admin = Setting::ambil('payment_whatsapp');

        if (blank($admin)) {
            return;
        }

        $label = $metode === 'tunai' ? 'Tunai ke panitia' : 'Dikonfirmasi panitia';

        // Ke semua nomor panitia, bukan satu: pengakuan uang masuk tanpa bukti
        // adalah kejadian yang harus dilihat lebih dari satu pasang mata.
        $wa->kirimBanyak($admin, "*Konfirmasi pembayaran TANPA bukti* ⚠️\n\n"
            ."Kode: {$registration->registration_code}\n"
            ."Peserta: {$registration->participant_name}\n"
            ."Kategori: {$registration->category->distance_label}\n"
            .'Nominal: Rp '.number_format($registration->amount, 0, ',', '.')."\n"
            ."BIB terbit: {$registration->bib_number}\n\n"
            ."Metode: {$label}\n"
            ."Catatan: {$catatan}\n"
            ."Dikonfirmasi oleh: {$panitia}\n"
            .'Waktu: '.now()->translatedFormat('d M Y, H:i'));
    }

    /**
     * Keputusan panitia hanya sah untuk bukti yang memang sedang menunggu verifikasi.
     * Ini mencegah pendaftaran yang sudah dibatalkan atau sudah lunas ikut terproses
     * lagi lewat tautan lama atau tombol yang ditekan dua kali.
     */
    private function blockedReason(Payment $payment): ?string
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            return $payment->status === Payment::STATUS_APPROVED
                ? 'Pembayaran ini sudah disetujui sebelumnya.'
                : 'Bukti pembayaran ini sudah ditolak sebelumnya.';
        }

        $registration = $payment->registration;

        return match ($registration->status) {
            Registration::STATUS_CANCELLED => 'Pendaftaran ini sudah dibatalkan, jadi pembayarannya tidak bisa diproses.',
            Registration::STATUS_CONFIRMED => 'Pendaftaran ini sudah berstatus lunas.',
            default => null,
        };
    }
}
