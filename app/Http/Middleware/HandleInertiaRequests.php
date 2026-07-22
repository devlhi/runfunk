<?php

namespace App\Http\Middleware;

use App\Models\NewsComment;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_panitia' => $user->isStaff(),
                    'is_developer' => $user->isDeveloper(),
                    'role_label' => $user->roleLabel(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'event' => [
                'name' => 'Gong Funrun 2026',
                'date_iso' => config('funrun.event_date'),
                'location' => config('funrun.location'),
            ],
            // Jumlah antrean verifikasi tampil sebagai lencana di sidebar panitia,
            // supaya bukti yang masuk tidak terlewat. Lazy, jadi hanya dihitung
            // saat Inertia benar-benar mengirim prop ini.
            'panitia' => $user?->isStaff() ? [
                'waiting' => fn () => Registration::where('status', Registration::STATUS_WAITING_VERIFICATION)->count(),
                // Isi lonceng notifikasi: siapa saja yang baru mendaftar.
                'feed' => fn () => $this->recentFeed(),
                'unread' => fn () => $this->unreadCount($user->notifications_seen_at),
            ] : null,
        ]);
    }

    /**
     * Isi lonceng: pendaftaran baru DAN komentar baru di berita, digabung lalu
     * diurutkan menurut waktu. Komentar ikut masuk karena halaman berita terbuka
     * untuk umum — tanpa ini, komentar spam bisa menempel berhari-hari tanpa
     * ada panitia yang menyadarinya.
     */
    private function recentFeed(): array
    {
        $pendaftaran = Registration::with('category')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Registration $r) => [
                'jenis' => 'pendaftaran',
                'url' => "/panitia/pendaftaran/{$r->id}",
                'name' => $r->participant_name,
                'category' => $r->category->distance_label,
                'code' => $r->registration_code,
                'status_label' => $r->statusLabel(),
                'waktu' => $r->created_at,
                'created_at' => $r->created_at->diffForHumans(),
            ]);

        $komentar = NewsComment::with(['user:id,name', 'news:id,slug,title'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (NewsComment $c) => [
                'jenis' => 'komentar',
                'url' => "/berita/{$c->news?->slug}#komentar",
                'name' => $c->user?->name ?? 'Pengguna dihapus',
                'category' => 'KOM',
                'code' => Str::limit($c->body, 40),
                'status_label' => 'Komentar di “'.Str::limit($c->news?->title ?? '—', 28).'”',
                'waktu' => $c->created_at,
                'created_at' => $c->created_at->diffForHumans(),
            ]);

        return $pendaftaran->concat($komentar)
            ->sortByDesc('waktu')
            ->take(10)
            ->map(fn ($item) => collect($item)->except('waktu')->all())
            ->values()
            ->all();
    }

    private function unreadCount(?Carbon $seenAt): int
    {
        $pendaftaran = Registration::when(
            $seenAt,
            fn ($q) => $q->where('created_at', '>', $seenAt)
        )->count();

        $komentar = NewsComment::when(
            $seenAt,
            fn ($q) => $q->where('created_at', '>', $seenAt)
        )->count();

        return $pendaftaran + $komentar;
    }
}
