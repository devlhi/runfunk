<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\RaceCategory;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Pilihan cakupan data. Bawaannya hanya yang lunas — itu yang aman dipakai memesan barang. */
    private const CAKUPAN = [
        'confirmed' => 'Hanya yang lunas',
        'aktif' => 'Lunas + menunggu verifikasi',
        'semua' => 'Semua kecuali batal',
    ];

    public function index(Request $request): Response
    {
        $cakupan = $request->query('cakupan', 'confirmed');
        $cakupan = array_key_exists($cakupan, self::CAKUPAN) ? $cakupan : 'confirmed';

        $kategoriSlug = $request->query('kategori');

        $dasar = fn () => Registration::query()
            ->when($kategoriSlug, fn (Builder $q) => $q->whereHas('category', fn ($c) => $c->where('slug', $kategoriSlug)))
            ->tap(fn (Builder $q) => match ($cakupan) {
                'confirmed' => $q->where('status', Registration::STATUS_CONFIRMED),
                'aktif' => $q->whereIn('status', [
                    Registration::STATUS_CONFIRMED,
                    Registration::STATUS_WAITING_VERIFICATION,
                ]),
                default => $q->where('status', '!=', Registration::STATUS_CANCELLED),
            });

        $total = (clone $dasar())->count();

        return Inertia::render('Panitia/Reports', [
            'filters' => ['cakupan' => $cakupan, 'kategori' => $kategoriSlug],
            'cakupanOptions' => collect(self::CAKUPAN)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'categories' => RaceCategory::orderBy('sort_order')->get(['slug', 'distance_label']),
            'total' => $total,
            'jersey' => $this->jerseyPerKategori($dasar),
            'gender' => $this->hitungKolom($dasar, 'gender', [
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
            ]),
            'usia' => $this->sebaranUsia($dasar),
            'kota' => $this->teratas($dasar, 'city', 10),
            'komunitas' => $this->teratas($dasar, 'community', 10),
            'golDarah' => $this->hitungKolom($dasar, 'blood_type'),
            'pemasukan' => $this->pemasukan($dasar),
        ]);
    }

    /**
     * Rekap ukuran jersey dipecah per kategori, karena jersey 5K dan 10K
     * dipesan terpisah — angka gabungan tidak bisa dipakai memesan.
     */
    private function jerseyPerKategori(callable $dasar): array
    {
        $baris = $dasar()
            ->join('race_categories', 'race_categories.id', '=', 'registrations.race_category_id')
            ->select('race_categories.distance_label as kategori', 'registrations.jersey_size as ukuran',
                DB::raw('COUNT(*) as jumlah'))
            ->groupBy('kategori', 'ukuran')
            ->get();

        $urutan = ['S', 'M', 'L', 'XL', 'XXL'];
        $hasil = [];

        foreach ($baris->groupBy('kategori') as $kategori => $isi) {
            $perUkuran = $isi->pluck('jumlah', 'ukuran');
            $hasil[] = [
                'kategori' => $kategori,
                'total' => (int) $isi->sum('jumlah'),
                'ukuran' => collect($urutan)->map(fn ($u) => [
                    'label' => $u,
                    'jumlah' => (int) ($perUkuran[$u] ?? 0),
                ])->all(),
            ];
        }

        return $hasil;
    }

    /** @param  array<string,string>  $label */
    private function hitungKolom(callable $dasar, string $kolom, array $label = []): array
    {
        return $dasar()
            ->select($kolom, DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull($kolom)
            ->where($kolom, '!=', '')
            ->groupBy($kolom)
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($r) => [
                'label' => $label[$r->{$kolom}] ?? $r->{$kolom},
                'jumlah' => (int) $r->jumlah,
            ])
            ->all();
    }

    /**
     * Kelompok usia memakai rentang yang biasa dipakai lomba lari untuk
     * menentukan kategori juara.
     */
    private function sebaranUsia(callable $dasar): array
    {
        $tanggal = $dasar()->whereNotNull('birth_date')->pluck('birth_date');

        $kelompok = [
            'Di bawah 18' => 0,
            '18–25' => 0,
            '26–35' => 0,
            '36–45' => 0,
            '46–55' => 0,
            'Di atas 55' => 0,
        ];

        foreach ($tanggal as $lahir) {
            $usia = $lahir->age;
            $kunci = match (true) {
                $usia < 18 => 'Di bawah 18',
                $usia <= 25 => '18–25',
                $usia <= 35 => '26–35',
                $usia <= 45 => '36–45',
                $usia <= 55 => '46–55',
                default => 'Di atas 55',
            };
            $kelompok[$kunci]++;
        }

        return collect($kelompok)
            ->map(fn ($jumlah, $label) => ['label' => $label, 'jumlah' => $jumlah])
            ->values()
            ->all();
    }

    private function teratas(callable $dasar, string $kolom, int $batas): array
    {
        return $dasar()
            ->select($kolom, DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull($kolom)
            ->where($kolom, '!=', '')
            ->groupBy($kolom)
            ->orderByDesc('jumlah')
            ->limit($batas)
            ->get()
            ->map(fn ($r) => ['label' => $r->{$kolom}, 'jumlah' => (int) $r->jumlah])
            ->all();
    }

    private function pemasukan(callable $dasar): array
    {
        return $dasar()
            ->join('race_categories', 'race_categories.id', '=', 'registrations.race_category_id')
            ->select('race_categories.distance_label as kategori',
                DB::raw('COUNT(*) as jumlah'), DB::raw('SUM(registrations.amount) as total'))
            ->groupBy('kategori')
            ->get()
            ->map(fn ($r) => [
                'label' => $r->kategori,
                'jumlah' => (int) $r->jumlah,
                'total' => (int) $r->total,
            ])
            ->all();
    }
}
