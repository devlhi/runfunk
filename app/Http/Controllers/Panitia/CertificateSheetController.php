<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\QrImage;
use App\Services\QrToken;
use App\Services\ResultService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cetak sertifikat banyak sekaligus.
 *
 * Mencetak satu per satu lewat halaman peserta tidak masuk akal begitu
 * finisher-nya ratusan: panitia harus membuka, mencetak, kembali, buka lagi.
 * Di sini seluruh finisher disusun jadi satu berkas — satu halaman A4 lanskap
 * per sertifikat — supaya cukup sekali tekan cetak.
 */
class CertificateSheetController extends Controller
{
    public function __construct(
        private readonly ResultService $service,
        private readonly QrToken $token,
        private readonly QrImage $qr,
    ) {}

    /** Halaman pemilihan: panitia memilih kategori lalu melihat berapa yang siap. */
    public function index(Request $request): Response
    {
        $kategori = $request->query('kategori');

        $siap = $this->dasar()
            ->when($kategori, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $kategori)));

        return Inertia::render('Panitia/CertificateSheetPicker', [
            'categories' => RaceCategory::orderBy('sort_order')->get(['slug', 'distance_label']),
            'filters' => ['kategori' => $kategori],
            'jumlah' => (clone $siap)->count(),
            // Ditampilkan supaya panitia tahu masih ada yang waktunya belum diisi
            // sebelum terlanjur mencetak sebagian.
            'belumAdaWaktu' => Registration::where('status', Registration::STATUS_CONFIRMED)
                ->whereNull('finish_seconds')
                ->count(),
            'contoh' => (clone $siap)->orderByRaw('CAST(bib_number AS UNSIGNED)')->limit(8)->get()
                ->map(fn (Registration $r) => [
                    'bib' => $r->bib_number,
                    'nama' => $r->participant_name,
                    'kategori' => $r->category->distance_label,
                    'waktu' => $this->service->keWaktu($r->finish_seconds),
                ]),
        ]);
    }

    /** Lembar cetaknya sendiri: tanpa kerangka panel supaya hasilnya bersih. */
    public function sheet(Request $request): Response
    {
        $kategori = $request->query('kategori');

        $daftar = $this->dasar()
            ->when($kategori, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $kategori)))
            ->orderBy('race_category_id')
            ->orderByRaw('CAST(bib_number AS UNSIGNED)')
            // Dibatasi supaya satu permintaan tidak menyusun ribuan QR sekaligus
            // dan membuat peramban panitia berhenti merespons.
            ->limit(300)
            ->get();

        return Inertia::render('Panitia/CertificateSheet', [
            'daftar' => $this->untukCetak($daftar),
            'acara' => $this->acara(),
        ]);
    }

    /** Hanya yang lunas DAN sudah punya catatan waktu yang bisa disertifikatkan. */
    private function dasar()
    {
        return Registration::with('category')
            ->where('status', Registration::STATUS_CONFIRMED)
            ->whereNotNull('finish_seconds');
    }

    private function untukCetak(Collection $daftar): array
    {
        return $daftar->map(fn (Registration $r) => [
            'id' => $r->id,
            'nama' => $r->participant_name,
            'bib' => $r->bib_number,
            'kategori' => $r->category->distance_label,
            'waktu' => $this->service->keWaktu($r->finish_seconds),
            'peringkat' => $r->rank_overall,
            'peringkat_gender' => $r->rank_gender,
            'gender' => $r->gender === 'P' ? 'Putri' : 'Putra',
            'kode' => $r->registration_code,
            'qr' => $this->qr->dataUri(
                route('certificate.verify', $this->token->untukSertifikat($r)),
                260
            ),
        ])->all();
    }

    private function acara(): array
    {
        return [
            'nama' => Setting::ambil('event_name') ?: 'Gong Fun Run 2026',
            'tanggal' => Carbon::parse(
                Setting::ambil('event_date') ?: config('funrun.event_date')
            )->translatedFormat('d F Y'),
            'lokasi' => Setting::ambil('location') ?: config('funrun.location'),
        ];
    }
}
