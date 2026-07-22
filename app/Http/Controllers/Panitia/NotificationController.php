<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Menandai semua pendaftaran yang ada sekarang sebagai sudah dilihat panitia
     * yang sedang masuk.
     */
    public function markSeen(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['notifications_seen_at' => now()])->save();

        return back();
    }
}
