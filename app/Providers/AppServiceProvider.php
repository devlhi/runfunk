<?php

namespace App\Providers;

use App\Services\MailGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->pasangKredensialEmail();
        $this->tolakDebugDiProduksi();
    }

    /**
     * APP_DEBUG=true di produksi menampilkan jejak galat lengkap ke pengunjung —
     * termasuk kredensial basis data dan potongan isi berkas .env. Ini kesalahan
     * penyetelan yang paling sering terjadi saat memindahkan situs dari Laragon
     * ke server, dan akibatnya paling parah. Lebih baik situsnya berhenti dengan
     * pesan jelas daripada berjalan sambil membuka isi perutnya.
     */
    private function tolakDebugDiProduksi(): void
    {
        if ($this->app->environment('production') && config('app.debug')) {
            throw new \RuntimeException(
                'APP_DEBUG harus false di produksi. Ubah di berkas .env lalu jalankan `php artisan config:clear`.'
            );
        }
    }

    /**
     * Kredensial SMTP disimpan di tabel settings supaya bisa diganti developer
     * lewat antarmuka. Dipasang di sini agar seluruh notifikasi memakainya.
     *
     * Dibungkus try/catch karena provider ini juga jalan saat `migrate` di basis
     * data kosong — tabel settings-nya belum ada, dan itu bukan alasan sah untuk
     * menggagalkan seluruh perintah artisan.
     */
    private function pasangKredensialEmail(): void
    {
        try {
            $this->app->make(MailGateway::class)->terapkan();
        } catch (\Throwable) {
            // Biarkan mailer bawaan dari .env yang dipakai.
        }
    }
}
