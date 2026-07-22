<?php

namespace Tests\Feature;

use App\Mail\UjiCobaEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pengaturan SMTP yang bisa diubah developer lewat antarmuka, lengkap dengan
 * tombol uji kirim.
 */
class MailGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $panitia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = User::create([
            'name' => 'Developer', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
        ]);
        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);
    }

    private function nyalakanGateway(array $ubah = []): void
    {
        Setting::simpan(array_merge([
            'mail_enabled' => '1',
            'mail_host' => 'smtp.contoh.id',
            'mail_port' => '587',
            'mail_scheme' => 'smtp',
            'mail_username' => 'panitia@gongfunrun.id',
            'mail_password' => 'sandi-rahasia',
            'mail_from_address' => 'panitia@gongfunrun.id',
            'mail_from_name' => 'Panitia Gong Fun Run',
        ], $ubah));
    }

    /* ---------------------------------------------------------- Servisnya */

    public function test_gateway_mati_selama_belum_dinyalakan(): void
    {
        $this->assertFalse(app(MailGateway::class)->aktif());
    }

    public function test_sakelar_menyala_tanpa_host_tetap_dianggap_mati(): void
    {
        // Kalau ini dianggap aktif, tiap notifikasi akan mencoba SMTP kosong.
        Setting::simpan(['mail_enabled' => '1', 'mail_from_address' => 'a@b.id']);

        $this->assertFalse(app(MailGateway::class)->aktif());
    }

    public function test_kredensial_tersimpan_menimpa_konfigurasi_mailer(): void
    {
        $this->nyalakanGateway();

        app(MailGateway::class)->terapkan();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.contoh.id', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('sandi-rahasia', config('mail.mailers.smtp.password'));
        $this->assertSame('panitia@gongfunrun.id', config('mail.from.address'));
        $this->assertSame('Panitia Gong Fun Run', config('mail.from.name'));
    }

    public function test_skema_asing_dijatuhkan_ke_smtp(): void
    {
        // Nilai di database bisa saja lawas atau disunting langsung; jangan sampai
        // nilai asing diteruskan apa adanya ke transport.
        $this->nyalakanGateway(['mail_scheme' => 'kacau']);

        app(MailGateway::class)->terapkan();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    public function test_gateway_mati_tidak_menyentuh_konfigurasi_bawaan(): void
    {
        config(['mail.mailers.smtp.host' => 'dari-env.test']);

        app(MailGateway::class)->terapkan();

        $this->assertSame('dari-env.test', config('mail.mailers.smtp.host'));
    }

    /* --------------------------------------------------------- Uji kirim */

    public function test_uji_kirim_mengirim_email_ke_alamat_tujuan(): void
    {
        Mail::fake();
        $this->nyalakanGateway();

        $this->actingAs($this->developer)
            ->post('/panitia/pengaturan/uji-email', ['email' => 'tujuan@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(
            UjiCobaEmail::class,
            fn (UjiCobaEmail $email) => $email->hasTo('tujuan@example.com')
                // Host & pengirim ikut di badan email supaya penerima tahu
                // konfigurasi mana yang barusan diuji.
                && $email->host === 'smtp.contoh.id:587'
                && $email->pengirim === 'panitia@gongfunrun.id'
        );
    }

    public function test_uji_kirim_ditolak_selama_gateway_belum_aktif(): void
    {
        Mail::fake();

        $this->actingAs($this->developer)
            ->post('/panitia/pengaturan/uji-email', ['email' => 'tujuan@example.com'])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_alamat_tujuan_wajib_valid(): void
    {
        Mail::fake();
        $this->nyalakanGateway();

        $this->actingAs($this->developer)
            ->post('/panitia/pengaturan/uji-email', ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_kegagalan_smtp_dilaporkan_sebagai_pesan_bukan_layar_galat(): void
    {
        $this->nyalakanGateway();

        // Transport sungguhan ke host yang tidak ada — persis seperti salah ketik
        // host di lapangan. Halamannya harus tetap hidup dan menjelaskan sebabnya.
        Setting::simpan(['mail_host' => 'smtp.host-yang-tidak-ada.invalid']);

        $hasil = app(MailGateway::class)->ujiKirim('tujuan@example.com');

        $this->assertFalse($hasil['ok']);
        $this->assertStringStartsWith('Gagal mengirim:', $hasil['pesan']);
    }

    /* ------------------------------------------------------- Penjagaannya */

    public function test_panitia_biasa_tidak_boleh_menguji_kirim(): void
    {
        Mail::fake();
        $this->nyalakanGateway();

        $this->actingAs($this->panitia)
            ->post('/panitia/pengaturan/uji-email', ['email' => 'tujuan@example.com'])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_tamu_tidak_boleh_menguji_kirim(): void
    {
        Mail::fake();
        $this->nyalakanGateway();

        $this->post('/panitia/pengaturan/uji-email', ['email' => 'tujuan@example.com'])
            ->assertRedirect('/masuk');

        Mail::assertNothingSent();
    }

    /* --------------------------------------------------------- Formulirnya */

    public function test_developer_bisa_menyimpan_pengaturan_email(): void
    {
        $this->actingAs($this->developer)
            ->patch('/panitia/pengaturan', $this->isiFormulir())
            ->assertSessionHasNoErrors();

        $this->assertSame('smtp.contoh.id', Setting::ambil('mail_host'));
        $this->assertSame('465', Setting::ambil('mail_port'));
        $this->assertSame('smtps', Setting::ambil('mail_scheme'));
        $this->assertSame('1', Setting::ambil('mail_enabled'));
    }

    public function test_gateway_tidak_bisa_dinyalakan_tanpa_host(): void
    {
        $this->actingAs($this->developer)
            ->patch('/panitia/pengaturan', $this->isiFormulir(['mail_host' => '']))
            ->assertSessionHasErrors('mail_host');

        $this->assertSame('0', Setting::ambil('mail_enabled'));
    }

    public function test_kata_sandi_smtp_tidak_pernah_dikirim_ke_browser(): void
    {
        $this->nyalakanGateway();

        $respon = $this->actingAs($this->developer)->get('/panitia/pengaturan');

        $respon->assertOk();
        // Bukan sekadar 'tidak muncul di prop': isi halamannya benar-benar bersih,
        // termasuk payload JSON Inertia yang tertanam di HTML.
        $respon->assertDontSee('sandi-rahasia', false);

        $respon->assertInertia(fn ($page) => $page
            ->where('settings.mail_password', '')
            ->where('settings.mail_password_terisi', true)
        );
    }

    public function test_kata_sandi_dikosongkan_berarti_biarkan_yang_lama(): void
    {
        $this->nyalakanGateway();

        $this->actingAs($this->developer)
            ->patch('/panitia/pengaturan', $this->isiFormulir(['mail_password' => '']));

        $this->assertSame('sandi-rahasia', Setting::ambil('mail_password'));
    }

    /** Formulir pengaturan mengirim semua kolom sekaligus, jadi isinya dilengkapi. */
    private function isiFormulir(array $ubah = []): array
    {
        return array_merge([
            'event_name' => 'Gong Fun Run 2026',
            'event_date' => '2026-10-31 06:00:00',
            'location' => 'Lapangan Tuladenggi',
            'payment_bank' => 'BRI',
            'payment_account' => '123456789',
            'payment_holder' => 'Panitia IKA',
            'payment_whatsapp' => '081234567890',
            'payment_deadline_hours' => 24,
            'registration_open' => true,
            'wa_enabled' => false,
            'wa_api_url' => null,
            'wa_api_key' => null,
            'wa_sender' => null,
            'mail_enabled' => true,
            'mail_host' => 'smtp.contoh.id',
            'mail_port' => 465,
            'mail_scheme' => 'smtps',
            'mail_username' => 'panitia@gongfunrun.id',
            'mail_password' => 'sandi-baru',
            'mail_from_address' => 'panitia@gongfunrun.id',
            'mail_from_name' => 'Panitia Gong Fun Run',
        ], $ubah);
    }
}
