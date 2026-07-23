<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'settings.all';

    /**
     * Daftar pengaturan yang bisa diubah developer, lengkap dengan nilai bawaan
     * dari config. Nilai bawaan dipakai selama barisnya belum ada di database,
     * jadi situs tetap jalan sebelum pengaturan pernah disimpan.
     */
    public static function definisi(): array
    {
        return [
            'event_name' => ['label' => 'Nama Acara', 'default' => 'Gong Fun Run 2026', 'type' => 'text'],
            'event_date' => ['label' => 'Tanggal & Jam Mulai', 'default' => config('funrun.event_date'), 'type' => 'datetime'],
            'location' => ['label' => 'Lokasi Start / Finis', 'default' => config('funrun.location'), 'type' => 'text'],
            'payment_bank' => ['label' => 'Nama Bank', 'default' => config('funrun.payment.bank_name'), 'type' => 'text'],
            'payment_account' => ['label' => 'Nomor Rekening', 'default' => config('funrun.payment.bank_account'), 'type' => 'text'],
            'payment_holder' => ['label' => 'Atas Nama', 'default' => config('funrun.payment.bank_holder'), 'type' => 'text'],
            'payment_whatsapp' => ['label' => 'WhatsApp Panitia', 'default' => config('funrun.payment.whatsapp'), 'type' => 'text'],
            'payment_deadline_hours' => ['label' => 'Batas Waktu Bayar (jam)', 'default' => config('funrun.payment_deadline_hours'), 'type' => 'number'],
            'registration_open' => ['label' => 'Pendaftaran Dibuka', 'default' => '1', 'type' => 'boolean'],

            // Kode verifikasi Google Search Console. Ditempel apa adanya dari
            // metode "HTML tag"; boleh isi kode saja atau seluruh tag <meta>.
            'google_verification' => ['label' => 'Kode Verifikasi Google', 'default' => '', 'type' => 'text'],

            // Sambutan di landing page. Namanya sengaja kosong dari awal: lebih
            // baik hanya menampilkan jabatan daripada memasang nama orang yang
            // salah di halaman depan.
            'chairman_name' => ['label' => 'Nama Ketua IKA', 'default' => '', 'type' => 'text'],
            'chairman_title' => ['label' => 'Jabatan', 'default' => 'Ketua IKA SMK Gotong Royong Telaga', 'type' => 'text'],
            'chairman_message' => ['label' => 'Isi Sambutan', 'default' => self::SAMBUTAN_BAWAAN, 'type' => 'textarea'],

            // Gateway WhatsApp (mpedia). Dibiarkan kosong berarti fitur kirim WA mati.
            'wa_enabled' => ['label' => 'Aktifkan Kirim WhatsApp', 'default' => '0', 'type' => 'boolean'],
            'wa_api_url' => ['label' => 'URL API mpedia', 'default' => '', 'type' => 'text'],
            'wa_api_key' => ['label' => 'API Key', 'default' => '', 'type' => 'secret'],
            'wa_sender' => ['label' => 'Nomor Pengirim', 'default' => '', 'type' => 'text'],

            // Gateway email (SMTP). Selama mati, Laravel tetap memakai mailer
            // bawaan dari .env — biasanya 'log' saat pengembangan.
            'mail_enabled' => ['label' => 'Aktifkan Kirim Email', 'default' => '0', 'type' => 'boolean'],
            'mail_host' => ['label' => 'Host SMTP', 'default' => '', 'type' => 'text'],
            'mail_port' => ['label' => 'Port', 'default' => '587', 'type' => 'number'],
            'mail_scheme' => ['label' => 'Keamanan Koneksi', 'default' => 'smtp', 'type' => 'text'],
            'mail_username' => ['label' => 'Nama Pengguna', 'default' => '', 'type' => 'text'],
            'mail_password' => ['label' => 'Kata Sandi', 'default' => '', 'type' => 'secret'],
            'mail_from_address' => ['label' => 'Email Pengirim', 'default' => '', 'type' => 'text'],
            'mail_from_name' => ['label' => 'Nama Pengirim', 'default' => 'Gong Fun Run 2026', 'type' => 'text'],
        ];
    }

    /** Pilihan keamanan koneksi SMTP. Laravel 11+ memakai scheme, bukan encryption. */
    public const SKEMA_MAIL = ['smtp', 'smtps'];

    /** Baris kosong memisahkan paragraf. */
    private const SAMBUTAN_BAWAAN = "Gong Fun Run 2026 lahir dari niat sederhana: mengumpulkan kembali alumni SMK Gotong Royong Telaga, lalu mengajak siapa saja ikut bergerak bersama.\n\nTidak perlu jago lari. Tidak perlu punya target waktu. Cukup datang, bawa keluarga dan teman, nikmati pagi di Gorontalo sambil sehat bareng.\n\nSampai jumpa di garis start.";

    /**
     * Sambutan dipecah jadi paragraf untuk ditampilkan di landing page.
     *
     * @return list<string>
     */
    public static function sambutanKetua(): array
    {
        $teks = self::ambil('chairman_message') ?: self::SAMBUTAN_BAWAAN;

        return collect(preg_split('/\R{2,}/', trim($teks)))
            ->map(fn (string $p) => trim($p))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Ubah tanggal apa pun ke bentuk yang dimengerti <input type="datetime-local">.
     *
     * Nilai bawaannya membawa offset zona (…+08:00) dan nilai tersimpan bisa
     * memakai spasi alih-alih T. Keduanya ditolak mentah-mentah oleh input itu,
     * sehingga isiannya tampil kosong dan formulir tidak bisa dikirim sama sekali.
     */
    public static function waktuLokal(?string $waktu): string
    {
        if (blank($waktu)) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($waktu)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    /** Pengaturan yang tidak boleh ikut terkirim apa adanya ke browser. */
    public static function rahasia(): array
    {
        return ['wa_api_key', 'mail_password'];
    }

    /** Semua pengaturan, digabung dengan nilai bawaan. Di-cache agar tidak query berulang. */
    public static function semua(): array
    {
        $tersimpan = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => self::query()->pluck('value', 'key')->all()
        );

        $hasil = [];
        foreach (self::definisi() as $key => $meta) {
            $hasil[$key] = $tersimpan[$key] ?? (string) $meta['default'];
        }

        return $hasil;
    }

    public static function ambil(string $key): ?string
    {
        return self::semua()[$key] ?? null;
    }

    public static function simpan(array $data): void
    {
        foreach ($data as $key => $value) {
            if (! array_key_exists($key, self::definisi())) {
                continue;
            }

            self::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
