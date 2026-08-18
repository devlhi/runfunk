<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\RaceCategory;
use App\Models\Setting;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Biaya pendaftaran baru diperlihatkan setelah pengunjung masuk. Angkanya
        // sengaja TIDAK ikut dikirim untuk tamu — kalau hanya disembunyikan lewat
        // CSS, nilainya tetap terbaca di sumber halaman.
        $bolehLihatHarga = $request->user() !== null;

        $categories = RaceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RaceCategory $category) => [
                'slug' => $category->slug,
                'name' => $category->name,
                'distance_label' => $category->distance_label,
                'tagline' => $category->tagline,
                'features' => $category->features ?? [],
                'price' => $bolehLihatHarga ? $category->price : null,
                'quota' => $category->quota,
                'remaining' => $category->remainingSlots(),
                'is_featured' => $category->is_featured,
                'is_sold_out' => $category->isSoldOut(),
            ]);

        return Inertia::render('Landing', [
            'categories' => $categories,
            'sponsors' => Sponsor::where('is_active', true)
                ->displayOrder()
                ->get()
                ->map(fn (Sponsor $s) => [
                    'name' => $s->name,
                    'tier' => $s->tier,
                    'website_url' => $s->website_url,
                    'display_type' => $s->display_type,
                    // Logo hanya dikirim kalau memang dipilih tampil sebagai logo,
                    // supaya landing tidak mengangkut berkas yang tak dipakai.
                    'logo_url' => $s->display_type === Sponsor::DISPLAY_LOGO ? $s->logoUrl() : null,
                ]),
            // Lima berita terbaru sebagai bukti acaranya benar-benar berjalan.
            // Kalau belum ada berita, bagiannya tidak dirender sama sekali.
            'news' => News::tayang()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (News $n) => [
                    'title' => $n->title,
                    'slug' => $n->slug,
                    'excerpt' => $n->ringkasan(150),
                    'cover_url' => $n->coverUrl(),
                    'published_at' => ($n->published_at ?? $n->created_at)->translatedFormat('d M Y'),
                ]),
            // Jumlah peserta yang sudah mendaftar tidak dibagikan ke publik.
            'stats' => [
                'total_quota' => (int) RaceCategory::where('is_active', true)->sum('quota'),
                'categories' => RaceCategory::where('is_active', true)->count(),
            ],
            'payment' => [
                // Nomor WA panitia bisa diganti lewat Pengaturan Acara; config
                // hanya nilai jatuh sebelum pengaturan pernah disimpan.
                'whatsapp' => Setting::ambil('payment_whatsapp') ?: config('funrun.payment.whatsapp'),
            ],
            'ketua' => [
                'nama' => Setting::ambil('chairman_name'),
                'jabatan' => Setting::ambil('chairman_title') ?: 'Ketua Ikatan Keluarga Alumni SMK Gotong Royong',
                'pesan' => Setting::sambutanKetua(),
            ],
        ]);
    }
}
