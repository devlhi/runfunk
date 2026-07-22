<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NewsManageController extends Controller
{
    /**
     * Daftarnya dipaginasi. Tanpa ini, seluruh berita ikut terkirim lengkap
     * dengan isi penuh tiap artikel — pada 100 berita payload-nya mencapai
     * 239 KB, sekitar seratus kali lipat halaman panel lainnya.
     */
    public function index(): Response
    {
        return Inertia::render('Panitia/News', [
            'news' => News::with('author')
                ->withCount('comments')
                ->latest('id')
                ->paginate(10)
                ->through(fn (News $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'slug' => $n->slug,
                    'excerpt' => $n->excerpt,
                    'body' => $n->body,
                    'cover_url' => $n->coverUrl(),
                    'is_published' => $n->is_published,
                    'published_at' => $n->published_at?->format('Y-m-d\TH:i'),
                    'published_label' => $n->published_at?->translatedFormat('d M Y, H:i'),
                    'views' => $n->views,
                    'comments_count' => $n->comments_count,
                    'author' => $n->author?->name ?? '—',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $berita = News::create([
            'title' => $data['title'],
            'slug' => News::buatSlug($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'is_published' => $data['is_published'],
            'published_at' => $data['is_published'] ? ($data['published_at'] ?? now()) : $data['published_at'] ?? null,
            'cover_path' => $this->simpanSampul($request),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Berita “{$berita->title}” disimpan.");
    }

    public function update(Request $request, News $news): RedirectResponse
    {
        $data = $this->validated($request);

        $sampulBaru = $this->simpanSampul($request);

        if ($sampulBaru && $news->cover_path) {
            // Berkas lama dibuang supaya folder tidak menumpuk gambar tak terpakai.
            Storage::disk('public')->delete($news->cover_path);
        }

        $news->update([
            'title' => $data['title'],
            'slug' => News::buatSlug($data['title'], $news->id),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'is_published' => $data['is_published'],
            'published_at' => $data['is_published'] ? ($data['published_at'] ?? $news->published_at ?? now()) : $data['published_at'] ?? null,
            'cover_path' => $sampulBaru ?? $news->cover_path,
        ]);

        return back()->with('success', 'Berita diperbarui.');
    }

    public function destroy(News $news): RedirectResponse
    {
        if ($news->cover_path) {
            Storage::disk('public')->delete($news->cover_path);
        }

        $judul = $news->title;
        $news->delete();

        return back()->with('success', "Berita “{$judul}” dihapus beserta komentarnya.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
            // mimetypes memeriksa isi berkas, bukan ekstensinya, supaya skrip
            // yang disamarkan sebagai .jpg tetap ditolak. Gambar ini tampil
            // publik, jadi hanya format gambar yang benar-benar aman diizinkan.
            'cover' => [
                'nullable', 'file', 'max:3072',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
            ],
        ], [
            'cover.mimetypes' => 'Sampul harus berupa gambar JPG, PNG, atau WEBP yang sah.',
            'cover.max' => 'Ukuran sampul maksimal 3 MB.',
        ], [
            'title' => 'judul',
            'excerpt' => 'ringkasan',
            'body' => 'isi berita',
            'cover' => 'gambar sampul',
        ]);
    }

    private function simpanSampul(Request $request): ?string
    {
        if (! $request->hasFile('cover')) {
            return null;
        }

        // Nama berkas dibuat acak oleh Laravel, jadi nama asli dari pengunggah
        // (yang bisa berisi karakter menyesatkan) tidak pernah dipakai.
        return $request->file('cover')->store('berita', 'public');
    }
}
