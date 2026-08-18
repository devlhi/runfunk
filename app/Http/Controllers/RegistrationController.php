<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $service) {}

    /**
     * Dashboard peserta — daftar semua pendaftaran miliknya.
     */
    public function index(Request $request): Response
    {
        $registrations = $request->user()->registrations()
            ->with(['category', 'latestPayment'])
            ->latest()
            ->get()
            ->map(fn (Registration $r) => $this->summarize($r));

        return Inertia::render('Peserta/Dashboard', [
            'registrations' => $registrations,
            'categories' => $this->categoryOptions(),
            // Pengumuman panitia tampil di sini karena dashboard adalah satu-satunya
            // tempat peserta pasti kembali untuk mengecek status pembayarannya.
            'announcements' => Announcement::where('is_published', true)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'level' => $a->level,
                    'created_at' => $a->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Peserta/RegistrationForm', [
            'categories' => $this->categoryOptions(),
            'selected' => $request->query('kategori'),
            'defaults' => [
                'participant_name' => $user->name,
                'participant_email' => $user->email,
                'participant_phone' => $user->phone,
                'gender' => $user->gender,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'city' => $user->city,
                'address' => $user->address,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'race_category_id' => ['required', 'exists:race_categories,id'],
            'participant_name' => ['required', 'string', 'max:120'],
            'participant_email' => ['required', 'email', 'max:180'],
            'participant_phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'in:L,P'],
            'birth_date' => ['required', 'date', 'before:today'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'jersey_size' => ['required', 'in:XS,S,M,L,XL,XXL,XXXL'],
            'blood_type' => ['nullable', 'in:A,B,AB,O'],
            'community' => ['nullable', 'string', 'max:120'],
            'emergency_name' => ['required', 'string', 'max:120'],
            'emergency_phone' => ['required', 'string', 'max:30'],
            'agreement' => ['accepted'],
        ], [
            'agreement.accepted' => 'Kamu harus menyetujui syarat & ketentuan peserta.',
        ], [
            'race_category_id' => 'kategori',
            'participant_name' => 'nama peserta',
            'participant_email' => 'email peserta',
            'participant_phone' => 'nomor WhatsApp',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            'city' => 'kota/kabupaten',
            'jersey_size' => 'ukuran jersey',
            'emergency_name' => 'nama kontak darurat',
            'emergency_phone' => 'nomor kontak darurat',
        ]);

        $category = RaceCategory::findOrFail($validated['race_category_id']);
        $registration = $this->service->create($request->user(), $category, $validated);

        return redirect()
            ->route('registrations.show', $registration)
            ->with('success', 'Pendaftaran dibuat. Selesaikan pembayaran untuk mengunci slotmu.');
    }

    public function show(Request $request, Registration $registration): Response
    {
        $this->authorizeOwner($request, $registration);
        $registration->load(['category', 'payments' => fn ($q) => $q->latest()]);

        return Inertia::render('Peserta/RegistrationDetail', [
            'registration' => [
                ...$this->summarize($registration),
                'address' => $registration->address,
                'blood_type' => $registration->blood_type,
                'community' => $registration->community,
                'emergency_name' => $registration->emergency_name,
                'emergency_phone' => $registration->emergency_phone,
                'participant_email' => $registration->participant_email,
                'participant_phone' => $registration->participant_phone,
                'gender' => $registration->gender,
                'birth_date' => $registration->birth_date?->format('Y-m-d'),
                'city' => $registration->city,
                'can_transfer' => SlotTransferController::bolehDialihkan($registration),
                'transferred_from' => $registration->transferred_from,
                'transferred_at' => $registration->transferred_at?->translatedFormat('d M Y, H:i'),
                'payments' => $registration->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'method' => $p->method,
                    'method_label' => $p->methodLabel(),
                    'amount' => $p->amount,
                    'sender_name' => $p->sender_name,
                    'sender_bank' => $p->sender_bank,
                    'proof_url' => $p->proofUrl(),
                    'status' => $p->status,
                    'reject_reason' => $p->reject_reason,
                    'paid_at' => $p->paid_at?->format('d M Y'),
                    'created_at' => $p->created_at->format('d M Y H:i'),
                ]),
            ],
            // Rekening yang tampil harus mengikuti Pengaturan Acara panitia.
            // Setting::ambil sudah jatuh ke nilai config kalau barisnya belum ada,
            // sama seperti pola batas waktu bayar di RegistrationService.
            'paymentInfo' => [
                'bank_name' => Setting::ambil('payment_bank') ?: config('funrun.payment.bank_name'),
                'bank_account' => Setting::ambil('payment_account') ?: config('funrun.payment.bank_account'),
                'bank_holder' => Setting::ambil('payment_holder') ?: config('funrun.payment.bank_holder'),
                'qris_name' => config('funrun.payment.qris_name'),
                'whatsapp' => Setting::ambil('payment_whatsapp') ?: config('funrun.payment.whatsapp'),
            ],
        ]);
    }

    public function cancel(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($request, $registration);

        if ($registration->isConfirmed()) {
            return back()->with('error', 'Pendaftaran yang sudah lunas tidak bisa dibatalkan sendiri. Hubungi panitia.');
        }

        $registration->update(['status' => Registration::STATUS_CANCELLED]);

        return redirect()->route('dashboard')->with('success', 'Pendaftaran dibatalkan.');
    }

    private function authorizeOwner(Request $request, Registration $registration): void
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
    }

    private function categoryOptions()
    {
        return RaceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RaceCategory $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'distance_label' => $c->distance_label,
                'tagline' => $c->tagline,
                'features' => $c->features ?? [],
                'price' => $c->price,
                'remaining' => $c->remainingSlots(),
                'is_featured' => $c->is_featured,
                'is_sold_out' => $c->isSoldOut(),
            ]);
    }

    private function summarize(Registration $registration): array
    {
        $payment = $registration->relationLoaded('latestPayment')
            ? $registration->latestPayment
            : $registration->payments()->latest()->first();

        return [
            'id' => $registration->id,
            'code' => $registration->registration_code,
            'participant_name' => $registration->participant_name,
            'jersey_size' => $registration->jersey_size,
            'category' => $registration->category->name,
            'category_slug' => $registration->category->slug,
            'distance_label' => $registration->category->distance_label,
            'amount' => $registration->amount,
            'status' => $registration->status,
            'status_label' => $registration->statusLabel(),
            'bib_number' => $registration->bib_number,
            'panitia_note' => $registration->panitia_note,
            'can_upload_proof' => $registration->canUploadProof(),
            // Sertifikat baru muncul setelah waktu finisnya dicatat panitia.
            'has_certificate' => $registration->finish_seconds !== null
                && $registration->status === Registration::STATUS_CONFIRMED,
            'finish_time' => $registration->finish_seconds
                ? sprintf('%02d:%02d:%02d',
                    intdiv($registration->finish_seconds, 3600),
                    intdiv($registration->finish_seconds % 3600, 60),
                    $registration->finish_seconds % 60)
                : null,
            'expires_at' => $registration->expires_at?->toIso8601String(),
            'created_at' => $registration->created_at->format('d M Y H:i'),
            'latest_payment_status' => $payment?->status,
        ];
    }
}
