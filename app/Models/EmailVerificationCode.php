<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationCode extends Model
{
    /** Umur kode sejak diterbitkan. Cukup untuk membuka email, tidak cukup untuk ditebak berhari-hari. */
    public const BERLAKU_MENIT = 60;

    /** Percobaan salah sebelum kode dianggap hangus dan harus diminta ulang. */
    public const MAKS_PERCOBAAN = 5;

    protected $fillable = ['user_id', 'code_hash', 'attempts', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kedaluwarsa(): bool
    {
        return $this->expires_at->isPast();
    }

    public function habisPercobaan(): bool
    {
        return $this->attempts >= self::MAKS_PERCOBAAN;
    }

    public function sisaPercobaan(): int
    {
        return max(0, self::MAKS_PERCOBAAN - $this->attempts);
    }
}
