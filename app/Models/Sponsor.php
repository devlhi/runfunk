<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    public const TIER_UTAMA = 'utama';

    public const TIER_PENDUKUNG = 'pendukung';

    public const TIER_MEDIA = 'media';

    protected $fillable = [
        'name',
        'tier',
        'website_url',
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
