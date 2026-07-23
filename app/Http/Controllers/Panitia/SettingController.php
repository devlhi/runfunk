<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MailGateway;
use App\Services\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        $nilai = Setting::semua();

        $nilai['event_date'] = Setting::waktuLokal($nilai['event_date'] ?? null);

        // API key tidak dikirim apa adanya ke browser — hanya penanda apakah
        // sudah terisi. Kalau dikirim penuh, siapa pun yang bisa membuka
        // halaman ini bisa menyalinnya dari sumber halaman.
        foreach (Setting::rahasia() as $key) {
            $nilai[$key.'_terisi'] = filled($nilai[$key] ?? null);
            $nilai[$key] = '';
        }

        return Inertia::render('Panitia/Settings', [
            'settings' => $nilai,
            'fields' => collect(Setting::definisi())
                ->map(fn ($meta, $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:180'],
            'payment_bank' => ['required', 'string', 'max:60'],
            'payment_account' => ['required', 'string', 'max:40'],
            'payment_holder' => ['required', 'string', 'max:120'],
            // Boleh berisi beberapa nomor, dipisah koma atau baris baru.
            'payment_whatsapp' => ['required', 'string', 'max:300'],
            'payment_deadline_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'registration_open' => ['required', 'boolean'],
            'google_verification' => ['nullable', 'string', 'max:255'],
            'chairman_name' => ['nullable', 'string', 'max:120'],
            'chairman_title' => ['nullable', 'string', 'max:120'],
            'chairman_message' => ['nullable', 'string', 'max:2000'],
            'wa_enabled' => ['required', 'boolean'],
            'wa_api_url' => ['nullable', 'url', 'max:255'],
            'wa_api_key' => ['nullable', 'string', 'max:255'],
            'wa_sender' => ['nullable', 'string', 'max:25'],
            'mail_enabled' => ['required', 'boolean'],
            'mail_host' => ['nullable', 'string', 'max:180'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            // Boleh kosong — MailGateway sudah jatuh ke 'smtp' kalau tak dikenali.
            'mail_scheme' => ['nullable', Rule::in(Setting::SKEMA_MAIL)],
            'mail_username' => ['nullable', 'string', 'max:180'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:180'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ], [], [
            'event_name' => 'nama acara',
            'event_date' => 'tanggal acara',
            'location' => 'lokasi',
            'payment_bank' => 'nama bank',
            'payment_account' => 'nomor rekening',
            'payment_holder' => 'atas nama',
            'payment_whatsapp' => 'WhatsApp panitia',
            'payment_deadline_hours' => 'batas waktu bayar',
            'mail_host' => 'host SMTP',
            'mail_port' => 'port SMTP',
            'mail_from_address' => 'email pengirim',
        ]);

        // Menyalakan sakelarnya tanpa host atau alamat pengirim hanya akan bikin
        // setiap notifikasi gagal diam-diam, jadi dicegah di sini.
        if ($data['mail_enabled'] && (blank($data['mail_host']) || blank($data['mail_from_address']))) {
            return back()->withErrors([
                'mail_host' => 'Host SMTP dan email pengirim wajib diisi sebelum gateway email dinyalakan.',
            ]);
        }

        $data['registration_open'] = $data['registration_open'] ? '1' : '0';
        $data['wa_enabled'] = $data['wa_enabled'] ? '1' : '0';
        $data['mail_enabled'] = $data['mail_enabled'] ? '1' : '0';

        // Kolom rahasia dikosongkan berarti "biarkan yang lama", bukan "hapus" —
        // formulirnya memang tidak pernah menampilkan nilai aslinya.
        foreach (Setting::rahasia() as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        Setting::simpan($data);

        return back()->with('success', 'Pengaturan acara disimpan dan langsung berlaku di seluruh situs.');
    }

    /**
     * Kirim satu email percobaan ke alamat yang diketik developer.
     *
     * Sengaja memakai pengaturan yang sudah TERSIMPAN, bukan isi formulir yang
     * belum disimpan — supaya yang diuji benar-benar konfigurasi yang dipakai
     * situs, bukan tebakan yang belum berlaku.
     */
    public function testMail(Request $request, MailGateway $mail): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ], [
            'email.required' => 'Isi alamat email tujuan percobaan.',
            'email.email' => 'Alamat email tujuan tidak valid.',
        ]);

        $hasil = $mail->ujiKirim($data['email']);

        return back()->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    /**
     * Kirim satu pesan WhatsApp percobaan.
     *
     * Sama seperti uji email: memakai kredensial yang sudah tersimpan, bukan isi
     * formulir yang belum disimpan, supaya yang teruji benar-benar konfigurasi
     * yang dipakai situs saat mengirim pengumuman.
     */
    public function testWa(Request $request, WhatsAppGateway $wa): RedirectResponse
    {
        $data = $request->validate([
            'nomor' => ['required', 'string', 'max:300'],
        ], [
            'nomor.required' => 'Isi nomor WhatsApp tujuan percobaan.',
        ], ['nomor' => 'nomor tujuan']);

        $hasil = $wa->kirimBanyak($data['nomor'], $this->pesanUjiWa());

        return back()->with($hasil['ok'] ? 'success' : 'error', $hasil['ok']
            ? 'Pesan percobaan: '.$hasil['pesan'].' Cek WhatsApp tujuan.'
            : $hasil['pesan']);
    }

    private function pesanUjiWa(): string
    {
        $acara = Setting::ambil('event_name') ?: 'Gong Fun Run 2026';

        return "*Uji coba gateway WhatsApp* ✅\n\n"
            ."Kalau pesan ini sampai, pengaturan gateway sudah benar dan pengumuman "
            ."ke peserta bisa dikirim lewat WhatsApp.\n\n"
            ."Pengirim: ".(Setting::ambil('wa_sender') ?: '—')."\n"
            .'Waktu: '.now()->translatedFormat('d M Y, H:i')." WITA\n\n"
            ."— Panitia {$acara}";
    }
}
