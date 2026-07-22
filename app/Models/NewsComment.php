<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsComment extends Model
{
    protected $fillable = ['news_id', 'user_id', 'body'];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Penulisnya sendiri boleh menghapus; panitia boleh menghapus milik siapa pun. */
    public function bolehDihapusOleh(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->id === $this->user_id || $user->isStaff();
    }
}
