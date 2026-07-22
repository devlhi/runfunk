<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'registration_id',
        'method',
        'amount',
        'sender_name',
        'sender_bank',
        'proof_path',
        'paid_at',
        'status',
        'reject_reason',
        'confirm_note',
        'reviewed_by',
        'reviewed_at',
    ];

    /** Metode yang dicatat panitia sendiri, tanpa peserta mengunggah bukti. */
    public const METODE_MANUAL = ['tunai', 'manual'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * URL bukti bayar selalu lewat route berautentikasi — berkasnya sendiri
     * disimpan di disk privat, bukan di folder publik.
     */
    public function proofUrl(): ?string
    {
        return $this->proof_path ? route('payments.proof', $this) : null;
    }

    public function tanpaBukti(): bool
    {
        return blank($this->proof_path);
    }

    public function proofIsPdf(): bool
    {
        return str_ends_with(mb_strtolower((string) $this->proof_path), '.pdf');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            'ewallet' => 'E-Wallet',
            'tunai' => 'Tunai ke Panitia',
            'manual' => 'Dikonfirmasi Panitia',
            default => $this->method,
        };
    }
}
