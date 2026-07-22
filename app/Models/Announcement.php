<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const LEVEL_INFO = 'info';

    public const LEVEL_PENTING = 'penting';

    protected $fillable = ['title', 'body', 'level', 'is_published', 'created_by', 'broadcast_at'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'broadcast_at' => 'datetime',
        ];
    }

    /** @return array<string, string> */
    public static function levels(): array
    {
        return [
            self::LEVEL_INFO => 'Info biasa',
            self::LEVEL_PENTING => 'Penting',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
