<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Registration;
use App\Models\Setting;
use App\Notifications\PengumumanPanitia;
use App\Services\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim satu pengumuman ke seluruh peserta yang berhak.
 *
 * Dijalankan lewat antrean karena mengirim ke ribuan nomor bisa memakan waktu
 * menit-menitan — jauh melewati batas waktu satu permintaan HTTP.
 */
class KirimPengumuman implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        private readonly int $announcementId,
        private readonly bool $lewatEmail,
        private readonly bool $lewatWhatsapp,
    ) {}

    public function handle(WhatsAppGateway $wa): void
    {
        $pengumuman = Announcement::find($this->announcementId);

        if (! $pengumuman) {
            return;
        }

        $namaAcara = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';
        $teksWa = "*{$pengumuman->title}*\n\n{$pengumuman->body}\n\n— Panitia {$namaAcara}";

        $terkirimEmail = 0;
        $terkirimWa = 0;
        $gagal = 0;

        // Hanya peserta yang pendaftarannya masih hidup — yang batal tidak perlu
        // lagi menerima kabar acara.
        Registration::with('user')
            ->whereIn('status', [
                Registration::STATUS_PENDING_PAYMENT,
                Registration::STATUS_WAITING_VERIFICATION,
                Registration::STATUS_CONFIRMED,
                Registration::STATUS_REJECTED,
            ])
            ->chunkById(200, function ($batch) use (&$terkirimEmail, &$terkirimWa, &$gagal, $wa, $pengumuman, $teksWa) {
                foreach ($batch as $registrasi) {
                    if ($this->lewatEmail && $registrasi->user) {
                        try {
                            $registrasi->user->notify(new PengumumanPanitia($pengumuman));
                            $terkirimEmail++;
                        } catch (\Throwable $e) {
                            $gagal++;
                            Log::warning('Gagal kirim email pengumuman', [
                                'registrasi' => $registrasi->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($this->lewatWhatsapp) {
                        $hasil = $wa->kirim($registrasi->participant_phone, $teksWa);
                        $hasil['ok'] ? $terkirimWa++ : $gagal++;

                        // Jeda singkat supaya gateway tidak menolak karena
                        // dianggap mengirim terlalu cepat.
                        usleep(400_000);
                    }
                }
            });

        Log::info('Broadcast pengumuman selesai', [
            'pengumuman' => $pengumuman->id,
            'email' => $terkirimEmail,
            'whatsapp' => $terkirimWa,
            'gagal' => $gagal,
        ]);
    }
}
