<?php

namespace App\Http\Controllers;

use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultPublicController extends Controller
{
    public function __construct(private readonly ResultService $service) {}

    /** Papan hasil untuk umum. */
    public function index(Request $request): Response
    {
        $slug = $request->query('kategori');
        $cari = trim((string) $request->query('cari', ''));

        $kategori = RaceCategory::orderBy('sort_order')->get();
        $terpilih = $slug
            ? $kategori->firstWhere('slug', $slug)
            : $kategori->first();

        $hasil = Registration::with('category')
            ->where('status', Registration::STATUS_CONFIRMED)
            ->whereNotNull('finish_seconds')
            ->when($terpilih, fn ($q) => $q->where('race_category_id', $terpilih->id))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('participant_name', 'like', "%{$cari}%")
                ->orWhere('bib_number', 'like', "%{$cari}%")))
            ->orderBy('finish_seconds')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Registration $r) => [
                'peringkat' => $r->rank_overall,
                'peringkat_gender' => $r->rank_gender,
                'bib' => $r->bib_number,
                // Hanya nama, kategori, dan waktu yang dibagikan ke publik —
                // email, telepon, dan kontak darurat tidak pernah ikut.
                'nama' => $r->participant_name,
                'gender' => $r->gender,
                'kota' => $r->city,
                'waktu' => $this->service->keWaktu($r->finish_seconds),
            ]);

        return Inertia::render('Results/Index', [
            'hasil' => $hasil,
            'categories' => $kategori->map(fn ($c) => ['slug' => $c->slug, 'label' => $c->distance_label]),
            'filters' => ['kategori' => $terpilih?->slug, 'cari' => $cari],
            'juara' => $terpilih ? $this->juara($terpilih) : [],
            'sudahAda' => Registration::whereNotNull('finish_seconds')->exists(),
            'namaAcara' => Setting::ambil('event_name') ?: 'Gong Fun Run 2026',
        ]);
    }

    /** Tiga tercepat putra dan putri di kategori terpilih. */
    private function juara(RaceCategory $kategori): array
    {
        $ambil = fn (string $gender) => Registration::where('race_category_id', $kategori->id)
            ->where('status', Registration::STATUS_CONFIRMED)
            ->whereNotNull('finish_seconds')
            ->where('gender', $gender)
            ->orderBy('finish_seconds')
            ->limit(3)
            ->get()
            ->map(fn (Registration $r) => [
                'bib' => $r->bib_number,
                'nama' => $r->participant_name,
                'waktu' => $this->service->keWaktu($r->finish_seconds),
            ])
            ->all();

        return ['putra' => $ambil('L'), 'putri' => $ambil('P')];
    }
}
