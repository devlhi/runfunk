<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $byStatus = Registration::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $revenue = (int) Registration::where('status', Registration::STATUS_CONFIRMED)->sum('amount');

        $categories = RaceCategory::orderBy('sort_order')->get()->map(fn (RaceCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'distance_label' => $c->distance_label,
            'price' => $c->price,
            'quota' => $c->quota,
            'taken' => $c->takenSlots(),
            'remaining' => $c->remainingSlots(),
            'confirmed' => $c->registrations()->where('status', Registration::STATUS_CONFIRMED)->count(),
        ]);

        $pending = Registration::with(['category', 'latestPayment'])
            ->where('status', Registration::STATUS_WAITING_VERIFICATION)
            ->oldest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'code' => $r->registration_code,
                'participant_name' => $r->participant_name,
                'category' => $r->category->distance_label,
                'amount' => $r->amount,
                'sender_name' => $r->latestPayment?->sender_name,
                'submitted_at' => $r->latestPayment?->created_at->diffForHumans(),
            ]);

        $kuotaTotal = (int) RaceCategory::where('is_active', true)->sum('quota');
        $terpakai = (int) $categories->sum('taken');

        return Inertia::render('Panitia/Dashboard', [
            'stats' => [
                'total' => (int) $byStatus->sum(),
                'confirmed' => (int) ($byStatus[Registration::STATUS_CONFIRMED] ?? 0),
                'waiting' => (int) ($byStatus[Registration::STATUS_WAITING_VERIFICATION] ?? 0),
                'pending_payment' => (int) ($byStatus[Registration::STATUS_PENDING_PAYMENT] ?? 0),
                'rejected' => (int) ($byStatus[Registration::STATUS_REJECTED] ?? 0),
                'cancelled' => (int) ($byStatus[Registration::STATUS_CANCELLED] ?? 0),
                'revenue' => $revenue,
                'proofs_today' => Payment::whereDate('created_at', today())->count(),
                'today' => Registration::whereDate('created_at', today())->count(),
                'quota_total' => $kuotaTotal,
                'quota_taken' => $terpakai,
            ],
            'categories' => $categories,
            'pendingQueue' => $pending,
            'trend' => $this->trenPendaftaran(),
            'event' => [
                'date_iso' => config('funrun.event_date'),
                'days_left' => (int) max(0, now()->startOfDay()->diffInDays(
                    Carbon::parse(config('funrun.event_date'))->startOfDay(),
                    false
                )),
            ],
        ]);
    }

    /**
     * Pendaftaran per hari selama dua pekan terakhir, untuk grafik batang kecil.
     * Hari tanpa pendaftaran tetap disertakan supaya bentuk grafiknya jujur —
     * kalau hari kosong dilewati, grafiknya terlihat lebih ramai dari kenyataan.
     */
    private function trenPendaftaran(): array
    {
        $mulai = today()->subDays(13);

        $perHari = Registration::query()
            ->where('created_at', '>=', $mulai)
            ->select(DB::raw('DATE(created_at) as tgl'), DB::raw('COUNT(*) as jumlah'))
            ->groupBy('tgl')
            ->pluck('jumlah', 'tgl');

        $hasil = [];
        for ($i = 0; $i < 14; $i++) {
            $tanggal = $mulai->copy()->addDays($i);
            $hasil[] = [
                'tanggal' => $tanggal->translatedFormat('d M'),
                'hari' => $tanggal->translatedFormat('D'),
                'jumlah' => (int) ($perHari[$tanggal->toDateString()] ?? 0),
            ];
        }

        return $hasil;
    }
}
