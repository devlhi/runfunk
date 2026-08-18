<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Sponsor extends Model
{
    public const TIER_UTAMA = 'utama';

    public const TIER_PENDUKUNG = 'pendukung';

    public const TIER_MEDIA = 'media';

    public const DISPLAY_LOGO = 'logo';

    public const DISPLAY_TEKS = 'teks';

    protected $fillable = [
        'name',
        'tier',
        'website_url',
        'logo_path',
        'display_type',
        'note',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function tiers(): array
    {
        return [
            self::TIER_UTAMA => 'Sponsor Utama',
            self::TIER_PENDUKUNG => 'Sponsor Pendukung',
            self::TIER_MEDIA => 'Media Partner',
        ];
    }

    public function tierLabel(): string
    {
        return self::tiers()[$this->tier] ?? $this->tier;
    }

    /** URL logo yang bisa dipakai <img>, null kalau sponsor belum punya logo. */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Hapus berkas logo dari disk public; aman dipanggil walau logo tidak ada. */
    public function deleteLogoFile(): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }
    }

    /**
     * Urutan tampil: sponsor utama dulu, lalu pendukung, lalu media partner.
     * Di dalam tiap tingkat, urutan mengikuti sort_order yang diatur panitia.
     */
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('FIELD(tier, ?, ?, ?)', [self::TIER_UTAMA, self::TIER_PENDUKUNG, self::TIER_MEDIA])
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
