<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    /** Daftar berita untuk umum. */
    public function index(): Response
    {
        $berita = News::tayang()
            ->with('author')
            ->withCount('comments')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9)
            ->through(fn (News $n) => $this->ringkas($n));

        return Inertia::render('News/Index', ['news' => $berita]);
    }

    public function show(Request $request, News $news): Response
    {
        // Berita yang belum tayang hanya boleh dilihat panitia yang menyiapkannya.
        abort_unless(
            $this->bolehLihat($news, $request),
            404
        );

        // Penghitung dibuat tidak menaikkan updated_at supaya urutan daftar
        // tidak berubah hanya karena beritanya dibaca.
        $news->timestamps = false;
        $news->increment('views');
        $news->timestamps = true;

        // Dipaginasi, bukan diambil semua: satu berita yang ramai bisa punya
        // ribuan komentar, dan seluruhnya ikut terkirim di muatan halaman.
        $komentar = $news->comments()
            ->with('user:id,name,role')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (NewsComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'author' => $c->user?->name ?? 'Pengguna dihapus',
                'is_staff' => (bool) $c->user?->isStaff(),
                'created_at' => $c->created_at->diffForHumans(),
                'can_delete' => $c->bolehDihapusOleh($request->user()),
            ]);

        return Inertia::render('News/Show', [
            'news' => [
                'title' => $news->title,
                'slug' => $news->slug,
                'body' => $news->body,
                'excerpt' => $news->ringkasan(),
                'cover_url' => $news->coverUrl(),
                'author' => $news->author?->name ?? 'Panitia',
                'published_at' => ($news->published_at ?? $news->created_at)->translatedFormat('d F Y'),
                'views' => $news->views,
                'is_published' => $news->is_published,
            ],
            'comments' => $komentar,
            'lainnya' => News::tayang()
                ->where('id', '!=', $news->id)
                ->latest('published_at')
                ->limit(3)
                ->get()
                ->map(fn (News $n) => $this->ringkas($n)),
        ]);
    }

    public function comment(Request $request, News $news): RedirectResponse
    {
        abort_unless($this->bolehLihat($news, $request), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'body.required' => 'Tulis dulu komentarnya.',
            'body.max' => 'Komentar maksimal 1000 karakter.',
        ], ['body' => 'komentar']);

        $news->comments()->create([
            'user_id' => $request->user()->id,
            // Disimpan apa adanya sebagai teks biasa. Tidak ada HTML yang
            // diizinkan, dan Vue meng-escape-nya saat ditampilkan.
            'body' => trim($data['body']),
        ]);

        return back()->with('success', 'Komentar terkirim.');
    }

    public function destroyComment(Request $request, NewsComment $comment): RedirectResponse
    {
        abort_unless($comment->bolehDihapusOleh($request->user()), 403);

        $comment->delete();

        return back()->with('success', 'Komentar dihapus.');
    }

    private function bolehLihat(News $news, Request $request): bool
    {
        if ($news->is_published && ($news->published_at === null || $news->published_at->isPast())) {
            return true;
        }

        return (bool) $request->user()?->isStaff();
    }

    private function ringkas(News $n): array
    {
        return [
            'title' => $n->title,
            'slug' => $n->slug,
            'excerpt' => $n->ringkasan(),
            'cover_url' => $n->coverUrl(),
            'author' => $n->author?->name ?? 'Panitia',
            'published_at' => ($n->published_at ?? $n->created_at)->translatedFormat('d M Y'),
            'comments_count' => $n->comments_count ?? 0,
        ];
    }
}
