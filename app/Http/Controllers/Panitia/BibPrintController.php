<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Services\QrImage;
use App\Services\QrToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BibPrintController extends Controller
{
    public function __construct(
        private readonly QrToken $token,
        private readonly QrImage $qr,
    ) {}

    /**
     * Halaman pemilihan: panitia memilih siapa saja yang nomor BIB-nya dicetak.
     */
    public function index(Request $request): Response
    {
        $category = $request->query('category');

        $dasar = fn () => Registration::query()
            ->where('status', Registration::STATUS_CONFIRMED)
            ->whereNotNull('bib_number')
            ->when($category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $category)));

        $registrations = $dasar()
            ->with('category')
            ->orderBy('race_category_id')
            ->orderByRaw('CAST(bib_number AS UNSIGNED)')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Panitia/BibPrint', [
            'registrations' => $registrations->through(
                fn (Registration $r) => $this->barisCetak($r)
            ),
            // Halaman ini alat pilih massal, bukan tabel baca: tanpa daftar id
            // yang utuh, "pilih semua" hanya akan mengenai halaman yang terbuka.
            // Yang dikirim cukup id-nya — ringan, sementara baris lengkapnya
            // tetap dibatasi per halaman supaya muatannya tidak membengkak.
            'semuaId' => $dasar()->orderBy('id')->pluck('id'),
            'categories' => RaceCategory::orderBy('sort_order')->get(['slug', 'distance_label']),
            'filters' => ['category' => $category],
        ]);
    }

    /**
     * Lembar cetak: hanya nomor yang dipilih, tanpa kerangka panel supaya
     * hasil cetakannya bersih.
     */
    public function sheet(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'string'],
        ]);

        $ids = collect(explode(',', $validated['ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(500);

        $registrations = Registration::with('category')
            ->whereIn('id', $ids)
            ->where('status', Registration::STATUS_CONFIRMED)
            ->whereNotNull('bib_number')
            ->orderBy('race_category_id')
            ->orderByRaw('CAST(bib_number AS UNSIGNED)')
            ->get();

        return Inertia::render('Panitia/BibSheet', [
            'registrations' => $this->mapForPrint($registrations),
        ]);
    }

    /**
     * QR satu peserta sebagai gambar tersendiri.
     *
     * Dipakai pratinjau di daftar peserta. Kalau data URI-nya ikut ditanam di
     * tiap baris, muatan halaman membengkak tiga kali lipat hanya untuk QR yang
     * kebanyakan tidak pernah dilihat — di sini cukup diminta saat dibuka.
     */
    public function qr(Registration $registration): HttpResponse
    {
        abort_unless($registration->bib_number !== null, 404);

        $svg = $this->qr->svg($this->token->untukPeserta($registration), 220);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            // Kodenya tetap sama sepanjang pendaftaran itu ada, jadi aman
            // disimpan peramban. Private: isinya khusus panitia.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function mapForPrint(Collection $registrations): array
    {
        return $registrations->map(fn (Registration $r) => $this->barisCetak($r))->all();
    }

    /** @return array<string, mixed> */
    private function barisCetak(Registration $r): array
    {
        return [
            'id' => $r->id,
            'bib_number' => $r->bib_number,
            'name' => $r->participant_name,
            'code' => $r->registration_code,
            'category' => $r->category->distance_label,
            'city' => $r->city,
            'jersey_size' => $r->jersey_size,
            'blood_type' => $r->blood_type,
            'emergency_name' => $r->emergency_name,
            'emergency_phone' => $r->emergency_phone,
            // Dipindai panitia saat membagikan race pack dan mencatat kehadiran.
            'qr' => $this->qr->dataUri($this->token->untukPeserta($r), 220),
        ];
    }
}
