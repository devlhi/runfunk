<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_path',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** URL publik memakai slug, bukan id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsComment::class);
    }

    /** Hanya berita yang sudah terbit DAN waktunya sudah tiba. */
    public function scopeTayang(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Slug unik dan aman untuk URL. Angka pembeda ditambahkan kalau judulnya
     * kebetulan sama, supaya berita lama tidak tertimpa.
     */
    public static function buatSlug(string $judul, ?int $abaikanId = null): string
    {
        $dasar = Str::slug($judul) ?: 'berita';
        $slug = $dasar;
        $n = 2;

        while (static::where('slug', $slug)
            ->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))
            ->exists()
        ) {
            $slug = $dasar.'-'.$n++;
        }

        return $slug;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset('storage/'.$this->cover_path) : null;
    }

    /** Ringkasan otomatis kalau panitia tidak mengisinya sendiri. */
    public function ringkasan(int $panjang = 160): string
    {
        return $this->excerpt ?: Str::limit(strip_tags($this->body), $panjang);
    }
}
