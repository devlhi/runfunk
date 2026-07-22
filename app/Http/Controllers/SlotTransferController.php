<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SlotTransferController extends Controller
{
    /**
     * Mengalihkan slot ke orang lain. Data peserta ditimpa seluruhnya, tapi
     * kepemilikan akun tidak berpindah — pemilik lama tetap yang mengelola
     * pembayaran dan e-tiketnya, persis seperti janji di FAQ bahwa slotnya
     * "dialihkan", bukan akunnya diserahkan.
     */
    public function store(Request $request, Registration $registration): RedirectResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);

        $this->pastikanBolehDialihkan($registration);

        $data = $request->validate([
            'participant_name' => ['required', 'string', 'max:120'],
            'participant_email' => ['required', 'email', 'max:180'],
            'participant_phone' => ['required', 'string', 'max:25'],
            'gender' => ['required', 'in:L,P'],
            'birth_date' => ['required', 'date', 'before:today'],
            'city' => ['required', 'string', 'max:80'],
            'jersey_size' => ['required', 'in:S,M,L,XL,XXL'],
            'blood_type' => ['nullable', 'in:A,B,AB,O'],
            'emergency_name' => ['required', 'string', 'max:120'],
            'emergency_phone' => ['required', 'string', 'max:25'],
            'konfirmasi' => ['accepted'],
        ], [
            'konfirmasi.accepted' => 'Centang dulu pernyataan bahwa pengalihan ini tidak bisa dibatalkan.',
        ], [
            'participant_name' => 'nama peserta pengganti',
            'participant_email' => 'email peserta pengganti',
            'participant_phone' => 'nomor WhatsApp',
            'birth_date' => 'tanggal lahir',
            'jersey_size' => 'ukuran jersey',
            'emergency_name' => 'nama kontak darurat',
            'emergency_phone' => 'nomor kontak darurat',
        ]);

        $pemilikLama = $registration->participant_name;

        $registration->update(collect($data)->except('konfirmasi')->all() + [
            'transferred_from' => $pemilikLama,
            'transferred_at' => now(),
        ]);

        return back()->with(
            'success',
            "Slot berhasil dialihkan dari {$pemilikLama} ke {$data['participant_name']}. "
            .'Nomor BIB dan kode pendaftaran tidak berubah.'
        );
    }

    private function pastikanBolehDialihkan(Registration $registration): void
    {
        // Hanya pendaftaran lunas yang punya "slot" untuk dialihkan; yang belum
        // bayar cukup dibatalkan lalu didaftarkan ulang atas nama yang baru.
        if ($registration->status !== Registration::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'participant_name' => 'Hanya pendaftaran yang sudah lunas yang bisa dialihkan.',
            ]);
        }

        if ($registration->transferred_at) {
            throw ValidationException::withMessages([
                'participant_name' => 'Slot ini sudah pernah dialihkan sekali dan tidak bisa dialihkan lagi.',
            ]);
        }

        if (! $this->masihDalamTenggat()) {
            throw ValidationException::withMessages([
                'participant_name' => 'Pengalihan slot sudah ditutup sejak H-7. Silakan hubungi panitia.',
            ]);
        }
    }

    /** Batas H-7 sesuai yang tertulis di FAQ landing page. */
    private function masihDalamTenggat(): bool
    {
        $tanggalAcara = Carbon::parse(Setting::ambil('event_date') ?: config('funrun.event_date'));

        return now()->lt($tanggalAcara->copy()->subDays(7));
    }

    /** Dipakai halaman detail untuk memutuskan menampilkan tombolnya atau tidak. */
    public static function bolehDialihkan(Registration $registration): bool
    {
        $tanggalAcara = Carbon::parse(Setting::ambil('event_date') ?: config('funrun.event_date'));

        return $registration->status === Registration::STATUS_CONFIRMED
            && $registration->transferred_at === null
            && now()->lt($tanggalAcara->copy()->subDays(7));
    }
}
