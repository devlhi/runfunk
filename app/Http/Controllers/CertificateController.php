<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Setting;
use App\Services\QrImage;
use App\Services\QrToken;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function __construct(
        private readonly ResultService $service,
        private readonly QrToken $token,
        private readonly QrImage $qr,
    ) {}

    /**
     * E-sertifikat peserta. Halaman ini dirancang untuk dicetak atau disimpan
     * sebagai PDF lewat dialog cetak browser — tidak perlu pustaka PDF di server.
     */
    public function show(Request $request, Registration $registration): Response
    {
        // Hanya pemiliknya sendiri atau panitia yang boleh membukanya. Sertifikat
        // memuat nama lengkap, jadi tidak dibuat terbuka lewat tautan tebakan.
        abort_unless(
            $registration->user_id === $request->user()->id || $request->user()->isStaff(),
            403
        );

        abort_unless($registration->status === Registration::STATUS_CONFIRMED, 404);

        // Sertifikat finisher baru sah setelah peserta benar-benar menyelesaikan
        // lomba — tanpa catatan waktu, tidak ada yang bisa disertifikatkan.
        abort_if($registration->finish_seconds === null, 404, 'Catatan waktu belum tersedia.');

        $registration->load('category');

        // QR menuju halaman pemeriksaan keaslian. Yang dipindai adalah alamat
        // penuhnya, jadi siapa pun yang menerima sertifikat ini cukup mengarahkan
        // kamera — tanpa perlu tahu situsnya atau punya akun.
        $tautanVerifikasi = route('certificate.verify', $this->token->untukSertifikat($registration));

        return Inertia::render('Certificate/Show', [
            'sertifikat' => [
                'nama' => $registration->participant_name,
                'bib' => $registration->bib_number,
                'kategori' => $registration->category->distance_label,
                'waktu' => $this->service->keWaktu($registration->finish_seconds),
                'peringkat' => $registration->rank_overall,
                'peringkat_gender' => $registration->rank_gender,
                'gender' => $registration->gender === 'P' ? 'Putri' : 'Putra',
                'kode' => $registration->registration_code,
                'qr' => $this->qr->dataUri($tautanVerifikasi, 260),
                'verifikasi_url' => $tautanVerifikasi,
            ],
            'acara' => [
                'nama' => Setting::ambil('event_name') ?: 'Gong Fun Run 2026',
                'tanggal' => Carbon::parse(
                    Setting::ambil('event_date') ?: config('funrun.event_date')
                )->translatedFormat('d F Y'),
                'lokasi' => Setting::ambil('location') ?: config('funrun.location'),
            ],
        ]);
    }
}
