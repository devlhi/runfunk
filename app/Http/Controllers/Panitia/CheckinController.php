<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\QrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckinController extends Controller
{
    public function index(Request $request): Response
    {
        $cari = trim((string) $request->query('cari', ''));
        $saring = $request->query('saring', 'semua');

        $peserta = Registration::with('category')
            ->where('status', Registration::STATUS_CONFIRMED)
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('bib_number', 'like', "%{$cari}%")
                    ->orWhere('participant_name', 'like', "%{$cari}%")
                    ->orWhere('registration_code', 'like', "%{$cari}%")
                    ->orWhere('participant_phone', 'like', "%{$cari}%");
            }))
            ->when($saring === 'belum_racepack', fn ($q) => $q->whereNull('racepack_at'))
            ->when($saring === 'belum_hadir', fn ($q) => $q->whereNull('checkin_at'))
            ->when($saring === 'sudah_hadir', fn ($q) => $q->whereNotNull('checkin_at'))
            ->orderByRaw('CAST(bib_number AS UNSIGNED)')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Registration $r) => [
                'id' => $r->id,
                'bib' => $r->bib_number,
                'nama' => $r->participant_name,
                'kode' => $r->registration_code,
                'kategori' => $r->category->distance_label,
                'jersey' => $r->jersey_size,
                'telepon' => $r->participant_phone,
                'racepack_at' => $r->racepack_at?->translatedFormat('d M, H:i'),
                'checkin_at' => $r->checkin_at?->translatedFormat('d M, H:i'),
            ]);

        $lunas = Registration::where('status', Registration::STATUS_CONFIRMED);

        return Inertia::render('Panitia/Checkin', [
            'peserta' => $peserta,
            'filters' => ['cari' => $cari, 'saring' => $saring],
            'stats' => [
                'lunas' => (clone $lunas)->count(),
                'racepack' => (clone $lunas)->whereNotNull('racepack_at')->count(),
                'hadir' => (clone $lunas)->whereNotNull('checkin_at')->count(),
            ],
        ]);
    }

    /**
     * Menandai lewat hasil pindaian QR.
     *
     * Menjawab JSON, bukan mengalihkan halaman: panitia memindai puluhan kartu
     * beruntun, dan memuat ulang seluruh halaman tiap kali membuat antrean
     * berhenti — persis masalah yang mau dihilangkan oleh pemindai ini.
     */
    public function pindai(Request $request, QrToken $token): JsonResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(['racepack', 'checkin'])],
        ]);

        $id = $token->bacaPeserta($data['kode']);

        if (! $id) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Kode QR tidak dikenali. Pastikan yang dipindai kartu BIB resmi.',
            ], 422);
        }

        $kolomWaktu = $data['jenis'].'_at';
        $label = $data['jenis'] === 'racepack' ? 'Race pack' : 'Kehadiran';

        /*
         * Dibaca dan ditulis di dalam satu transaksi berkunci.
         *
         * Tanpa kunci, dua panitia yang memindai kartu yang sama pada saat
         * bersamaan sama-sama melihat kolomnya masih kosong — lalu keduanya
         * membagikan race pack untuk satu orang. Dengan kunci, yang kedua
         * menunggu, membaca hasil yang pertama, dan diberi tahu "sudah ditandai".
         */
        $hasil = DB::transaction(function () use ($id, $kolomWaktu, $label, $data, $request) {
            $registrasi = Registration::whereKey($id)->lockForUpdate()->first();

            if (! $registrasi || $registrasi->status !== Registration::STATUS_CONFIRMED) {
                return ['kode' => 422, 'isi' => [
                    'ok' => false,
                    'pesan' => 'Peserta tidak ditemukan atau pendaftarannya belum lunas.',
                ]];
            }

            $registrasi->load('category');

            // Sudah tertandai bukan dianggap galat: di antrean panjang, kartu yang
            // sama terpindai dua kali itu wajar. Yang penting panitia diberi tahu
            // supaya tidak memberikan race pack dua kali.
            if ($registrasi->{$kolomWaktu} !== null) {
                return ['kode' => 200, 'isi' => [
                    'ok' => true,
                    'ulangan' => true,
                    'pesan' => "{$label} sudah ditandai ".$registrasi->{$kolomWaktu}->translatedFormat('H:i').'.',
                    'peserta' => $this->ringkas($registrasi),
                ]];
            }

            $registrasi->update([
                $kolomWaktu => now(),
                $data['jenis'].'_by' => $request->user()->id,
            ]);

            return ['kode' => 200, 'isi' => [
                'ok' => true,
                'ulangan' => false,
                'pesan' => "{$label} ditandai.",
                'peserta' => $this->ringkas($registrasi),
            ]];
        });

        return response()->json($hasil['isi'], $hasil['kode']);
    }

    /** @return array<string, mixed> */
    private function ringkas(Registration $r): array
    {
        return [
            'id' => $r->id,
            'bib' => $r->bib_number,
            'nama' => $r->participant_name,
            'kategori' => $r->category->distance_label,
            'jersey' => $r->jersey_size,
            'racepack_at' => $r->racepack_at?->translatedFormat('d M, H:i'),
            'checkin_at' => $r->checkin_at?->translatedFormat('d M, H:i'),
        ];
    }

    /**
     * Menandai atau membatalkan penandaan. Sengaja bisa dibatalkan karena salah
     * centang di tengah antrean panjang itu hal yang wajar terjadi.
     */
    public function toggle(Request $request, Registration $registration): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['racepack', 'checkin'])],
            'nilai' => ['required', 'boolean'],
        ]);

        abort_unless(
            $registration->status === Registration::STATUS_CONFIRMED,
            422,
            'Hanya pendaftaran lunas yang bisa ditandai.'
        );

        $kolomWaktu = $data['jenis'].'_at';
        $kolomOleh = $data['jenis'].'_by';

        $registration->update([
            $kolomWaktu => $data['nilai'] ? now() : null,
            $kolomOleh => $data['nilai'] ? $request->user()->id : null,
        ]);

        $label = $data['jenis'] === 'racepack' ? 'Race pack' : 'Kehadiran';
        $aksi = $data['nilai'] ? 'ditandai' : 'dibatalkan';

        return back()->with('success', "{$label} {$registration->participant_name} {$aksi}.");
    }
}
