<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
            'category' => $request->query('category', ''),
        ];

        $registrations = Registration::with(['category', 'latestPayment'])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('participant_name', 'like', "%{$search}%")
                        ->orWhere('registration_code', 'like', "%{$search}%")
                        ->orWhere('participant_email', 'like', "%{$search}%")
                        ->orWhere('participant_phone', 'like', "%{$search}%")
                        ->orWhere('bib_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category'], fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Registration $r) => [
                'id' => $r->id,
                'code' => $r->registration_code,
                'participant_name' => $r->participant_name,
                'participant_phone' => $r->participant_phone,
                'category' => $r->category->distance_label,
                'jersey_size' => $r->jersey_size,
                'amount' => $r->amount,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'bib_number' => $r->bib_number,
                // Dipakai pratinjau kartu BIB langsung dari daftar. Ikut dikirim
                // di sini alih-alih diambil terpisah saat diklik: datanya pendek,
                // sudah boleh dilihat panitia, dan pratinjaunya jadi tanpa jeda.
                'city' => $r->city,
                'blood_type' => $r->blood_type,
                'emergency_name' => $r->emergency_name,
                'emergency_phone' => $r->emergency_phone,
                'has_proof' => (bool) $r->latestPayment,
                // Bukti yang sedang menunggu keputusan bisa disetujui langsung
                // dari daftar, tanpa membuka halaman detail satu per satu.
                'pending_payment_id' => $r->latestPayment?->status === Payment::STATUS_PENDING
                    ? $r->latestPayment->id
                    : null,
                'can_confirm_manual' => in_array($r->status, [
                    Registration::STATUS_PENDING_PAYMENT,
                    Registration::STATUS_REJECTED,
                ], true),
                'created_at' => $r->created_at->format('d M Y'),
            ]);

        return Inertia::render('Panitia/Registrations', [
            'registrations' => $registrations,
            'filters' => $filters,
            'categories' => RaceCategory::orderBy('sort_order')->get(['slug', 'distance_label']),
            'statuses' => [
                ['value' => 'pending_payment', 'label' => 'Menunggu Pembayaran'],
                ['value' => 'waiting_verification', 'label' => 'Menunggu Verifikasi'],
                ['value' => 'confirmed', 'label' => 'Terdaftar & Lunas'],
                ['value' => 'rejected', 'label' => 'Bukti Ditolak'],
                ['value' => 'cancelled', 'label' => 'Dibatalkan'],
            ],
        ]);
    }

    public function show(Registration $registration): Response
    {
        $registration->load(['category', 'user', 'verifier', 'payments' => fn ($q) => $q->latest()]);

        return Inertia::render('Panitia/RegistrationDetail', [
            'registration' => [
                'id' => $registration->id,
                'code' => $registration->registration_code,
                'status' => $registration->status,
                'status_label' => $registration->statusLabel(),
                'bib_number' => $registration->bib_number,
                'amount' => $registration->amount,
                'category' => $registration->category->name,
                'distance_label' => $registration->category->distance_label,
                'participant_name' => $registration->participant_name,
                'participant_email' => $registration->participant_email,
                'participant_phone' => $registration->participant_phone,
                'gender' => $registration->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                'birth_date' => $registration->birth_date?->format('d M Y'),
                'city' => $registration->city,
                'address' => $registration->address,
                'jersey_size' => $registration->jersey_size,
                'blood_type' => $registration->blood_type,
                'community' => $registration->community,
                'emergency_name' => $registration->emergency_name,
                'emergency_phone' => $registration->emergency_phone,
                'panitia_note' => $registration->panitia_note,
                'account_name' => $registration->user->name,
                'account_email' => $registration->user->email,
                'verified_by' => $registration->verifier?->name,
                'verified_at' => $registration->verified_at?->format('d M Y H:i'),
                'created_at' => $registration->created_at->format('d M Y H:i'),
                'payments' => $registration->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'method_label' => $p->methodLabel(),
                    'amount' => $p->amount,
                    'sender_name' => $p->sender_name,
                    'sender_bank' => $p->sender_bank,
                    'proof_url' => $p->proofUrl(),
                    'is_pdf' => $p->proofIsPdf(),
                    'status' => $p->status,
                    'reject_reason' => $p->reject_reason,
                    'confirm_note' => $p->confirm_note,
                    'paid_at' => $p->paid_at?->format('d M Y'),
                    'created_at' => $p->created_at->format('d M Y H:i'),
                    'reviewer' => $p->reviewer?->name,
                ]),
            ],
        ]);
    }

    public function updateNote(Request $request, Registration $registration): RedirectResponse
    {
        $validated = $request->validate([
            'panitia_note' => ['nullable', 'string', 'max:500'],
        ]);

        $registration->update($validated);

        return back()->with('success', 'Catatan panitia disimpan.');
    }

    public function export(Request $request): StreamedResponse
    {
        $status = $request->query('status');

        $filename = 'peserta-gong-funrun-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($status) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar

            fputcsv($out, [
                'Kode', 'BIB', 'Nama Peserta', 'Kategori', 'Jenis Kelamin', 'Tanggal Lahir',
                'Email', 'WhatsApp', 'Kota', 'Jersey', 'Gol. Darah', 'Komunitas',
                'Kontak Darurat', 'No. Darurat', 'Biaya', 'Status', 'Tanggal Daftar',
            ]);

            Registration::with('category')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderBy('race_category_id')
                ->orderBy('bib_number')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, array_map($this->csvSafe(...), [
                            $r->registration_code,
                            $r->bib_number ?? '-',
                            $r->participant_name,
                            $r->category->distance_label,
                            $r->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                            $r->birth_date?->format('Y-m-d'),
                            $r->participant_email,
                            $r->participant_phone,
                            $r->city,
                            $r->jersey_size,
                            $r->blood_type ?? '-',
                            $r->community ?? '-',
                            $r->emergency_name,
                            $r->emergency_phone,
                            $r->amount,
                            $r->statusLabel(),
                            $r->created_at->format('Y-m-d H:i'),
                        ]));
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Excel & Google Sheets menjalankan isi sel yang diawali = + - @ sebagai rumus.
     * Nama peserta diisi sendiri oleh pendaftar, jadi nilainya dinetralkan dulu
     * dengan kutip satu supaya tidak bisa dipakai menyerang komputer panitia.
     */
    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
