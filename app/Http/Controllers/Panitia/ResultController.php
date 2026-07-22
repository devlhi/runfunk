<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Services\ResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    public function __construct(private readonly ResultService $service) {}

    public function index(Request $request): Response
    {
        $cari = trim((string) $request->query('cari', ''));
        $kategori = $request->query('kategori');
        $saring = $request->query('saring', 'semua');

        $peserta = Registration::with('category')
            ->where('status', Registration::STATUS_CONFIRMED)
            ->whereNotNull('bib_number')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('bib_number', 'like', "%{$cari}%")
                ->orWhere('participant_name', 'like', "%{$cari}%")))
            ->when($kategori, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $kategori)))
            ->when($saring === 'belum', fn ($q) => $q->whereNull('finish_seconds'))
            ->when($saring === 'sudah', fn ($q) => $q->whereNotNull('finish_seconds'))
            ->orderByRaw('CAST(bib_number AS UNSIGNED)')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Registration $r) => [
                'id' => $r->id,
                'bib' => $r->bib_number,
                'nama' => $r->participant_name,
                'kategori' => $r->category->distance_label,
                'gender' => $r->gender,
                'waktu' => $this->service->keWaktu($r->finish_seconds),
                'peringkat' => $r->rank_overall,
            ]);

        $lunas = Registration::where('status', Registration::STATUS_CONFIRMED);

        return Inertia::render('Panitia/Results', [
            'peserta' => $peserta,
            'filters' => ['cari' => $cari, 'kategori' => $kategori, 'saring' => $saring],
            'categories' => RaceCategory::orderBy('sort_order')->get(['slug', 'distance_label']),
            'stats' => [
                'lunas' => (clone $lunas)->count(),
                'sudah' => (clone $lunas)->whereNotNull('finish_seconds')->count(),
            ],
        ]);
    }

    public function store(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless(
            $registration->status === Registration::STATUS_CONFIRMED,
            422,
            'Hanya peserta lunas yang bisa dicatat waktunya.'
        );

        $data = $request->validate([
            // Boleh dikosongkan untuk menghapus catatan waktu yang salah.
            'waktu' => ['nullable', 'string', 'max:12'],
        ], [], ['waktu' => 'waktu finis']);

        $detik = null;

        if (filled($data['waktu'])) {
            $detik = $this->service->keDetik($data['waktu']);

            if ($detik === null) {
                throw ValidationException::withMessages([
                    'waktu' => 'Format waktu tidak dikenali. Tulis jam:menit:detik (01:02:03) '
                        .'atau menit:detik (90:00). Maksimal 12 jam.',
                ]);
            }

            if ($detik < 300) {
                // Lari 5K tercepat di dunia pun di atas 12 menit; di bawah 5
                // menit hampir pasti salah ketik, bukan rekor dunia.
                throw ValidationException::withMessages([
                    'waktu' => 'Waktu di bawah 5 menit tidak masuk akal — periksa lagi angkanya.',
                ]);
            }
        }

        $registration->forceFill([
            'finish_seconds' => $detik,
            'finished_at' => $detik ? now() : null,
        ])->save();

        $this->service->hitungPeringkat();

        return back()->with(
            'success',
            $detik
                ? "Waktu {$registration->participant_name} dicatat: {$this->service->keWaktu($detik)}."
                : "Catatan waktu {$registration->participant_name} dihapus."
        );
    }
}
