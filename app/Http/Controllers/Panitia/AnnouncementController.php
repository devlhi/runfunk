<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Jobs\KirimPengumuman;
use App\Models\Announcement;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panitia/Announcements', [
            'announcements' => Announcement::with('author')
                ->latest()
                ->paginate(10)
                ->through(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'level' => $a->level,
                    'level_label' => Announcement::levels()[$a->level] ?? $a->level,
                    'is_published' => $a->is_published,
                    'broadcast_at' => $a->broadcast_at?->translatedFormat('d M Y, H:i'),
                    'author' => $a->author?->name ?? '—',
                    'created_at' => $a->created_at->translatedFormat('d M Y, H:i'),
                ]),
            'levels' => collect(Announcement::levels())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'waAktif' => app(WhatsAppGateway::class)->aktif(),
            'jumlahPenerima' => Registration::whereIn('status', [
                Registration::STATUS_PENDING_PAYMENT,
                Registration::STATUS_WAITING_VERIFICATION,
                Registration::STATUS_CONFIRMED,
                Registration::STATUS_REJECTED,
            ])->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Pengumuman tayang di dashboard semua peserta.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return back()->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    /**
     * Uji kirim ke satu nomor sebelum menyebar ke semua peserta. Ini sengaja
     * dibuat wajib tersedia: sekali broadcast terkirim, pesannya tidak bisa
     * ditarik kembali dari ribuan ponsel.
     */
    public function test(Request $request, Announcement $announcement, WhatsAppGateway $wa): RedirectResponse
    {
        $data = $request->validate([
            'nomor' => ['required', 'string', 'max:25'],
        ], [], ['nomor' => 'nomor WhatsApp']);

        $nama = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';
        $teks = "*{$announcement->title}*\n\n{$announcement->body}\n\n— Panitia {$nama}";

        $hasil = $wa->kirim($data['nomor'], $teks);

        return back()->with($hasil['ok'] ? 'success' : 'error', 'Uji kirim: '.$hasil['pesan']);
    }

    public function broadcast(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'boolean'],
            'whatsapp' => ['required', 'boolean'],
        ]);

        if (! $data['email'] && ! $data['whatsapp']) {
            return back()->with('error', 'Pilih minimal satu saluran pengiriman.');
        }

        if (! $announcement->is_published) {
            return back()->with('error', 'Terbitkan pengumumannya dulu sebelum dikirim.');
        }

        $penerima = Registration::whereIn('status', [
            Registration::STATUS_PENDING_PAYMENT,
            Registration::STATUS_WAITING_VERIFICATION,
            Registration::STATUS_CONFIRMED,
            Registration::STATUS_REJECTED,
        ])->count();

        if ($penerima === 0) {
            return back()->with('error', 'Belum ada peserta yang bisa dikirimi pengumuman.');
        }

        KirimPengumuman::dispatch($announcement->id, $data['email'], $data['whatsapp']);

        $announcement->update(['broadcast_at' => now()]);

        $saluran = collect([
            $data['email'] ? 'email' : null,
            $data['whatsapp'] ? 'WhatsApp' : null,
        ])->filter()->implode(' dan ');

        return back()->with(
            'success',
            "Pengiriman ke {$penerima} peserta lewat {$saluran} dimulai. Prosesnya berjalan di latar belakang."
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::in(array_keys(Announcement::levels()))],
            'is_published' => ['required', 'boolean'],
        ], [], [
            'title' => 'judul',
            'body' => 'isi pengumuman',
            'level' => 'tingkat',
        ]);
    }
}
