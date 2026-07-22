<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_WAITING_VERIFICATION = 'waiting_verification';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'registration_code',
        'user_id',
        'race_category_id',
        'participant_name',
        'participant_email',
        'participant_phone',
        'gender',
        'birth_date',
        'city',
        'address',
        'jersey_size',
        'blood_type',
        'community',
        'emergency_name',
        'emergency_phone',
        'amount',
        'bib_number',
        'status',
        'panitia_note',
        'verified_by',
        'verified_at',
        'expires_at',
        'racepack_at',
        'racepack_by',
        'checkin_at',
        'checkin_by',
        'transferred_from',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'racepack_at' => 'datetime',
            'checkin_at' => 'datetime',
            'transferred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RaceCategory::class, 'race_category_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_WAITING_VERIFICATION,
            self::STATUS_CONFIRMED,
        ]);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Peserta boleh mengunggah bukti bayar saat belum bayar atau setelah ditolak.
     */
    public function canUploadProof(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_REJECTED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_PAYMENT => 'Menunggu Pembayaran',
            self::STATUS_WAITING_VERIFICATION => 'Menunggu Verifikasi',
            self::STATUS_CONFIRMED => 'Terdaftar & Lunas',
            self::STATUS_REJECTED => 'Bukti Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => $this->status,
        };
    }
}
