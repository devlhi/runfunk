<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pembungkus gateway WhatsApp mpedia.
 *
 * Kredensialnya diambil dari tabel settings, bukan .env, supaya developer bisa
 * menggantinya lewat antarmuka. Kalau belum diisi atau sakelarnya mati, semua
 * pengiriman dilewati diam-diam — situs tetap jalan tanpa gateway.
 */
class WhatsAppGateway
{
    public function aktif(): bool
    {
        return Setting::ambil('wa_enabled') === '1'
            && filled(Setting::ambil('wa_api_url'))
            && filled(Setting::ambil('wa_api_key'));
    }

    /**
     * Nomor Indonesia ditulis warga dengan berbagai gaya: 0812…, +62812…,
     * 62812…, bahkan dengan spasi dan strip. Gateway hanya menerima format
     * 62 tanpa tanda baca, jadi dinormalkan dulu di sini.
     */
    public function normalkanNomor(?string $nomor): ?string
    {
        if (blank($nomor)) {
            return null;
        }

        $bersih = preg_replace('/\D+/', '', $nomor);

        if (blank($bersih)) {
            return null;
        }

        if (str_starts_with($bersih, '0')) {
            $bersih = '62'.substr($bersih, 1);
        } elseif (! str_starts_with($bersih, '62')) {
            $bersih = '62'.$bersih;
        }

        // Nomor Indonesia yang sah panjangnya 10–15 digit termasuk kode negara.
        return (strlen($bersih) >= 10 && strlen($bersih) <= 15) ? $bersih : null;
    }

    /**
     * Pecah daftar nomor yang ditulis panitia menjadi nomor-nomor yang sah.
     *
     * Satu kolom isian menampung beberapa nomor supaya laporan pembayaran tidak
     * bergantung pada satu ponsel saja — kalau bendahara sedang tidak memegang
     * HP-nya, ketua panitia tetap menerima kabarnya. Dipisah koma, titik koma,
     * atau baris baru, karena ketiganya sama-sama wajar diketik orang.
     *
     * @return list<string> nomor ternormalisasi, tanpa duplikat
     */
    public function daftarNomor(?string $daftar): array
    {
        if (blank($daftar)) {
            return [];
        }

        return collect(preg_split('/[,;\r\n]+/', $daftar))
            ->map(fn ($n) => $this->normalkanNomor(trim($n)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kirim satu pesan ke semua nomor pada daftar.
     *
     * Kegagalan satu nomor tidak menghentikan sisanya: nomor yang salah ketik di
     * urutan pertama tidak boleh membuat panitia lain ikut tidak dikabari.
     *
     * @return array{ok: bool, pesan: string, terkirim: int, gagal: int}
     */
    public function kirimBanyak(?string $daftar, string $pesan): array
    {
        $nomor = $this->daftarNomor($daftar);

        if ($nomor === []) {
            return ['ok' => false, 'pesan' => 'Tidak ada nomor WhatsApp yang sah pada daftar.', 'terkirim' => 0, 'gagal' => 0];
        }

        $berhasil = 0;
        $gagal = [];

        foreach ($nomor as $tujuan) {
            $hasil = $this->kirim($tujuan, $pesan);
            $hasil['ok'] ? $berhasil++ : $gagal[] = $tujuan;
        }

        if ($berhasil === 0) {
            return [
                'ok' => false,
                'pesan' => 'Gagal mengirim ke semua nomor ('.count($gagal).').',
                'terkirim' => 0,
                'gagal' => count($gagal),
            ];
        }

        return [
            'ok' => true,
            'pesan' => $gagal === []
                ? "Terkirim ke {$berhasil} nomor."
                : "Terkirim ke {$berhasil} nomor, gagal ".count($gagal).' ('.implode(', ', $gagal).').',
            'terkirim' => $berhasil,
            'gagal' => count($gagal),
        ];
    }

    /**
     * @return array{ok: bool, pesan: string}
     */
    public function kirim(?string $nomor, string $pesan): array
    {
        if (! $this->aktif()) {
            return ['ok' => false, 'pesan' => 'Gateway WhatsApp belum diaktifkan di Pengaturan Acara.'];
        }

        $tujuan = $this->normalkanNomor($nomor);

        if (! $tujuan) {
            return ['ok' => false, 'pesan' => 'Nomor WhatsApp tidak valid.'];
        }

        try {
            $respon = Http::asForm()
                ->timeout(15)
                ->post(rtrim(Setting::ambil('wa_api_url'), '/'), [
                    'api_key' => Setting::ambil('wa_api_key'),
                    'sender' => Setting::ambil('wa_sender'),
                    'number' => $tujuan,
                    'message' => $pesan,
                ]);

            if ($respon->successful()) {
                return ['ok' => true, 'pesan' => 'Terkirim ke '.$tujuan];
            }

            // Isi respons ikut dicatat karena tiap gateway punya format galat
            // sendiri — tanpa ini penyebab gagalnya sulit ditebak.
            Log::warning('Gagal kirim WhatsApp', ['nomor' => $tujuan, 'status' => $respon->status(), 'body' => $respon->body()]);

            return ['ok' => false, 'pesan' => 'Gateway menolak: HTTP '.$respon->status()];
        } catch (\Throwable $e) {
            Log::warning('Gateway WhatsApp tidak bisa dihubungi', ['error' => $e->getMessage()]);

            return ['ok' => false, 'pesan' => 'Gateway tidak bisa dihubungi: '.$e->getMessage()];
        }
    }
}
