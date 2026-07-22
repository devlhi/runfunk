<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\QrImage;
use App\Services\QrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kartu tanda panitia beserta pemeriksaannya.
 *
 * Gunanya di lapangan: memastikan yang mengaku panitia — di pintu masuk, di
 * meja race pack, di area start — memang terdaftar. Kartunya membawa QR
 * bertanda tangan, jadi tidak bisa dibuat sendiri dengan aplikasi QR biasa.
 */
class CommitteeCardController extends Controller
{
    public function __construct(
        private readonly QrToken $token,
        private readonly QrImage $qr,
    ) {}

    public function index(): Response
    {
        $pengelola = User::whereIn('role', array_keys(User::rolesPengelola()))
            ->orderByRaw('FIELD(role, ?, ?)', [User::ROLE_DEVELOPER, User::ROLE_PANITIA])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->baris($u));

        return Inertia::render('Panitia/Cards', [
            'pengelola' => $pengelola,
            'acara' => $this->acara(),
        ]);
    }

    /** Lembar cetak: hanya kartu yang dipilih, tanpa kerangka panel. */
    public function sheet(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'string'],
        ]);

        $ids = collect(explode(',', $validated['ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(200);

        $kartu = User::whereIn('id', $ids)
            ->whereIn('role', array_keys(User::rolesPengelola()))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->baris($u, penuh: true));

        return Inertia::render('Panitia/CardSheet', [
            'kartu' => $kartu,
            'acara' => $this->acara(),
        ]);
    }

    /** Halaman pemindai untuk memeriksa kartu di lapangan. */
    public function validasi(): Response
    {
        return Inertia::render('Panitia/CardValidate');
    }

    /**
     * Memeriksa kartu hasil pindaian.
     *
     * Dua hal diperiksa terpisah: tanda tangannya sah, DAN pemegangnya masih
     * panitia dengan versi kartu terkini. Kartu yang hilang dinonaktifkan dengan
     * mencetak ulang (versinya naik), dan orang yang sudah bukan panitia lagi
     * otomatis ditolak walau kartunya masih di tangan.
     */
    public function periksa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:120'],
        ]);

        $isi = $this->token->bacaPanitia($data['kode']);

        if (! $isi) {
            return response()->json([
                'sah' => false,
                'pesan' => 'Kode tidak dikenali. Ini bukan kartu panitia yang diterbitkan panel.',
            ]);
        }

        $user = User::find($isi['id']);

        if (! $user || ! $user->isStaff()) {
            return response()->json([
                'sah' => false,
                'pesan' => 'Pemegang kartu ini sudah bukan panitia.',
            ]);
        }

        if ($user->card_version !== $isi['versi']) {
            return response()->json([
                'sah' => false,
                'pesan' => 'Kartu ini sudah dicetak ulang, jadi yang lama tidak berlaku. Minta kartu terbarunya.',
                'orang' => ['nama' => $user->name],
            ]);
        }

        return response()->json([
            'sah' => true,
            'pesan' => 'Kartu sah.',
            'orang' => [
                'nama' => $user->name,
                'jabatan' => $user->card_title ?: $user->roleLabel(),
                'peran' => $user->roleLabel(),
                'telepon' => $user->phone,
                'nomor' => $this->nomorKartu($user),
            ],
        ]);
    }

    /**
     * Terbitkan ulang kartu: versinya dinaikkan sehingga kartu lama langsung
     * ditolak saat dipindai. Dipakai kalau kartunya hilang atau dicuri.
     */
    public function terbitkanUlang(User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        $user->increment('card_version');

        return back()->with(
            'success',
            "Kartu {$user->name} diterbitkan ulang. Kartu lamanya sekarang ditolak saat dipindai — cetak yang baru."
        );
    }

    /**
     * Pas foto panitia.
     *
     * Disimpan di disk privat, bukan folder publik: ini foto wajah orang, dan
     * tidak ada alasan siapa pun di internet bisa mengunduhnya lewat tautan
     * tebakan. Diambil lewat rute berautentikasi, sama seperti bukti bayar.
     */
    public function unggahFoto(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        $request->validate([
            // mimetypes memeriksa isi berkasnya, bukan sekadar ekstensi — berkas
            // yang disamarkan jadi .jpg tetap ditolak.
            'foto' => [
                'required', 'file', 'max:3072',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
            ],
        ], [
            'foto.mimetypes' => 'Pas foto harus gambar JPG, PNG, atau WEBP yang sah.',
            'foto.max' => 'Ukuran foto maksimal 3 MB.',
        ], ['foto' => 'pas foto']);

        $lama = $user->photo_path;

        $user->update([
            'photo_path' => $request->file('foto')->store("pas-foto/{$user->id}", 'local'),
        ]);

        // Foto lama dibuang supaya folder tidak menumpuk berkas tak terpakai.
        if ($lama) {
            Storage::disk('local')->delete($lama);
        }

        return back()->with('success', "Pas foto {$user->name} disimpan.");
    }

    public function hapusFoto(User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        if ($user->photo_path) {
            Storage::disk('local')->delete($user->photo_path);
            $user->update(['photo_path' => null]);
        }

        return back()->with('success', "Pas foto {$user->name} dihapus. Kartunya kembali memakai bingkai tempel.");
    }

    /** Menyajikan pas foto ke pengelola yang sedang masuk. */
    public function foto(User $user): StreamedResponse
    {
        abort_unless($user->isStaff() && $user->photo_path, 404);
        abort_unless(Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response(
            $user->photo_path,
            null,
            ['Content-Disposition' => 'inline', 'Cache-Control' => 'private, max-age=3600']
        );
    }

    public function simpanJabatan(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);

        $data = $request->validate([
            'card_title' => ['nullable', 'string', 'max:60'],
        ], [], ['card_title' => 'jabatan']);

        $user->update(['card_title' => $data['card_title'] ?: null]);

        return back()->with('success', "Jabatan {$user->name} disimpan.");
    }

    /** @return array<string, mixed> */
    private function baris(User $u, bool $penuh = false): array
    {
        $data = [
            'id' => $u->id,
            'nama' => $u->name,
            'jabatan' => $u->card_title,
            'peran' => $u->roleLabel(),
            'is_developer' => $u->isDeveloper(),
            'telepon' => $u->phone,
            'nomor' => $this->nomorKartu($u),
            'versi' => $u->card_version,
            // Rute berautentikasi, bukan tautan berkas langsung.
            'foto' => $u->photo_path ? route('panitia.cards.photo', $u) : null,
        ];

        // QR hanya ikut di lembar cetak. Di halaman daftar, menanam data URI
        // untuk tiap orang hanya membengkakkan muatan tanpa pernah dilihat.
        if ($penuh) {
            $data['qr'] = $this->qr->dataUri($this->token->untukPanitia($u), 240);
        }

        return $data;
    }

    /**
     * Nomor kartu yang mudah dibacakan lewat radio atau telepon saat ada yang
     * perlu dicek tanpa memindai — misal panitia di pos jauh tanpa sinyal.
     */
    private function nomorKartu(User $u): string
    {
        return 'PAN-'.str_pad((string) $u->id, 3, '0', STR_PAD_LEFT)
            .'-'.str_pad((string) $u->card_version, 2, '0', STR_PAD_LEFT);
    }

    /** @return array<string, string> */
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
