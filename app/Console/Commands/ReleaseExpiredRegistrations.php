<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Command;

class ReleaseExpiredRegistrations extends Command
{
    protected $signature = 'funrun:release-expired';

    protected $description = 'Batalkan pendaftaran yang tidak dibayar sampai batas waktu, agar slotnya kembali tersedia';

    public function handle(): int
    {
        $released = Registration::query()
            ->where('status', Registration::STATUS_PENDING_PAYMENT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => Registration::STATUS_CANCELLED,
                'panitia_note' => 'Dibatalkan otomatis karena pembayaran tidak diselesaikan sampai batas waktu.',
            ]);

        $this->info("{$released} pendaftaran kedaluwarsa dilepas.");

        return self::SUCCESS;
    }
}
