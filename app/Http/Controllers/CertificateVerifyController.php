<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Setting;
use App\Services\QrToken;
use App\Services\ResultService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pemeriksaan keaslian sertifikat — dibuka lewat QR yang tercetak di
 * sertifikatnya.
 *
 * Sengaja TERBUKA tanpa perlu masuk: yang memeriksa justru orang luar — panitia
 * lomba lain, sekolah, atau tempat kerja yang menerima sertifikat itu. Kalau
 * harus punya akun dulu, QR-nya kehilangan seluruh gunanya.
 *
 * Yang ditampilkan tidak melebihi papan hasil yang memang sudah publik: nama,
 * kategori, catatan waktu, dan peringkat. Nomor telepon, email, tanggal lahir,
 * dan kontak darurat tidak pernah ikut.
 */
class CertificateVerifyController extends Controller
{
    public function __construct(
        private readonly QrToken $token,
        private readonly ResultService $service,
    ) {}

    public function show(string $kode): Response
    {
        $id = $this->token->bacaSertifikat($kode);

        $registration = $id
            ? Registration::with('category')
                ->where('status', Registration::STATUS_CONFIRMED)
                ->whereNotNull('finish_seconds')
                ->find($id)
            : null;

        return Inertia::render('Certificate/Verify', [
            'sah' => (bool) $registration,
            'sertifikat' => $registration ? [
                'nama' => $registration->participant_name,
                'bib' => $registration->bib_number,
                'kategori' => $registration->category->distance_label,
                'waktu' => $this->service->keWaktu($registration->finish_seconds),
                'peringkat' => $registration->rank_overall,
                'peringkat_gender' => $registration->rank_gender,
                'gender' => $registration->gender === 'P' ? 'Putri' : 'Putra',
                'kode' => $registration->registration_code,
            ] : null,
            'acara' => [
                'nama' => Setting::ambil('event_name') ?: 'Gong Fun Run 2026',
                'tanggal' => \Illuminate\Support\Carbon::parse(
                    Setting::ambil('event_date') ?: config('funrun.event_date')
                )->translatedFormat('d F Y'),
                'lokasi' => Setting::ambil('location') ?: config('funrun.location'),
            ],
        ]);
    }
}
