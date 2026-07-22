<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaceCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'distance_label',
        'tagline',
        'description',
        'features',
        'price',
        'quota',
        'bib_start',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Slot yang sudah terpakai: sudah lunas atau sedang dalam proses pembayaran.
     *
     * Pendaftaran yang belum dibayar sampai lewat batas waktu tidak lagi dihitung,
     * supaya slot yang ditinggalkan otomatis kembali tersedia untuk peserta lain.
     */
    public function takenSlots(): int
    {
        return $this->registrations()
            ->where(function ($query) {
                $query->whereIn('status', [
                    Registration::STATUS_WAITING_VERIFICATION,
                    Registration::STATUS_CONFIRMED,
                ])->orWhere(function ($pending) {
                    $pending->where('status', Registration::STATUS_PENDING_PAYMENT)
                        ->where(function ($window) {
                            $window->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
            })
            ->count();
    }

    public function remainingSlots(): int
    {
        return max(0, $this->quota - $this->takenSlots());
    }

    public function isSoldOut(): bool
    {
        return $this->remainingSlots() <= 0;
    }
}
