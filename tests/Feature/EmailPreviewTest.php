<?php

namespace Tests\Feature;

use App\Mail\VerifikasiEmail;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pratinjau desain email untuk developer, dan uji kirim WhatsApp.
 */
class EmailPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $panitia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
            'email_verified_at' => now(),
        ]);
        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
            'email_verified_at' => now(),
        ]);
    }

    /* ------------------------------------------------------- Pratinjau */

    public function test_developer_bisa_membuka_daftar_template(): void
    {
        $this->actingAs($this->developer)
            ->get('/panitia/pratinjau-email')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/EmailPreview')
                ->has('templates', 2));
    }

    /* --------------------------------------------------- Menyunting isi */

    public function test_developer_bisa_menyunting_isi_email(): void
    {
        $this->actingAs($this->developer)
            ->patch('/panitia/pratinjau-email/verifikasi', [
                'subject' => 'Kode kamu {{kode}}',
                'isi' => '<p>Halo {{nama}}, ini isi baru.</p>',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('email_templates', ['key' => 'verifikasi']);

        // Isi baru harus benar-benar dipakai email yang dikirim, bukan hanya
        // tersimpan lalu diabaikan.
        $isi = (new VerifikasiEmail(nama: 'Rian', kode: '123456', tautan: 'https://a.test'))->render();

        $this->assertStringContainsString('ini isi baru', $isi);
        $this->assertStringContainsString('Halo Rian', $isi);
    }

    public function test_judul_email_ikut_memakai_placeholder(): void
    {
        $this->actingAs($this->developer)->patch('/panitia/pratinjau-email/verifikasi', [
            'subject' => 'Kode kamu {{kode}}',
            'isi' => '<p>Isi.</p>',
        ]);

        $email = new VerifikasiEmail(nama: 'Rian', kode: '482915', tautan: 'https://a.test');

        $this->assertSame('Kode kamu 482915', $email->envelope()->subject);
    }

    public function test_template_bisa_dikembalikan_ke_bawaan(): void
    {
        $this->actingAs($this->developer)->patch('/panitia/pratinjau-email/verifikasi', [
            'subject' => 'Diubah', 'isi' => '<p>Diubah total.</p>',
        ]);

        $this->actingAs($this->developer)
            ->delete('/panitia/pratinjau-email/verifikasi')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('email_templates', ['key' => 'verifikasi']);

        $isi = (new VerifikasiEmail(nama: 'Rian', kode: '123456', tautan: 'https://a.test'))->render();
        $this->assertStringContainsString('Verifikasi Email Saya', $isi);
    }

    public function test_pratinjau_draf_tidak_ikut_menyimpan(): void
    {
        $this->actingAs($this->developer)
            ->post('/panitia/pratinjau-email/verifikasi/draf', ['isi' => '<p>Coba dulu.</p>'])
            ->assertOk()
            ->assertSee('Coba dulu.', false);

        $this->assertDatabaseMissing('email_templates', ['key' => 'verifikasi']);
    }

    /* ----------------------------------------------------- Penyaringannya */

    public function test_skrip_dibuang_saat_disimpan_bukan_hanya_saat_ditampilkan(): void
    {
        $this->actingAs($this->developer)->patch('/panitia/pratinjau-email/verifikasi', [
            'subject' => 'Uji',
            'isi' => '<p>Aman</p><script>alert(1)</script><img src=x onerror="alert(2)">',
        ]);

        $tersimpan = EmailTemplate::where('key', 'verifikasi')->value('body_html');

        // Kalau hanya disaring saat render, isi berbahaya tetap mengendap di
        // basis data dan ikut terbawa ke mana pun ia dipakai nanti.
        $this->assertStringNotContainsString('<script', $tersimpan);
        $this->assertStringNotContainsString('alert(1)', $tersimpan);
        $this->assertStringNotContainsString('onerror', $tersimpan);
        $this->assertStringContainsString('Aman', $tersimpan);
    }

    public function test_isi_template_tidak_pernah_dijalankan_sebagai_blade(): void
    {
        // Blade dikompilasi jadi PHP. Kalau isi dari formulir ikut dikompilasi,
        // siapa pun yang bisa membuka halaman ini bisa menjalankan kode di server.
        $this->actingAs($this->developer)->patch('/panitia/pratinjau-email/verifikasi', [
            'subject' => 'Uji',
            'isi' => '<p>{{ 7*6 }} @php echo 99; @endphp {{ config("app.key") }}</p>',
        ]);

        $isi = (new VerifikasiEmail(nama: 'Rian', kode: '123456', tautan: 'https://a.test'))->render();

        // Sintaksnya bertahan APA ADANYA — itulah buktinya tidak dikompilasi.
        // Memeriksa "99 tidak muncul" saja tidak cukup: angka itu tetap muncul
        // sebagai teks biasa, dan asersi seperti itu tidak bisa membedakan
        // "dicetak harfiah" dari "dieksekusi".
        $this->assertStringContainsString('{{ 7*6 }}', $isi);
        $this->assertStringContainsString('@php echo 99; @endphp', $isi);

        // Hasil evaluasinya tidak ada di mana pun.
        $this->assertStringNotContainsString('42', $isi);
        $this->assertStringNotContainsString(config('app.key'), $isi);
    }

    public function test_tautan_berskema_javascript_dilucuti(): void
    {
        $this->actingAs($this->developer)->patch('/panitia/pratinjau-email/verifikasi', [
            'subject' => 'Uji',
            'isi' => '<p><a href="javascript:alert(1)">Klik</a></p>',
        ]);

        $tersimpan = EmailTemplate::where('key', 'verifikasi')->value('body_html');

        $this->assertStringNotContainsString('javascript:', $tersimpan);
        // Teksnya tetap ada — yang dibuang hanya tautannya.
        $this->assertStringContainsString('Klik', $tersimpan);
    }

    public function test_nama_peserta_berisi_tag_tidak_jadi_html(): void
    {
        // Placeholder diganti dengan nilai yang sudah di-escape, jadi nama aneh
        // tidak bisa menyuntikkan markup ke badan email orang lain.
        $isi = (new VerifikasiEmail(
            nama: '<script>alert(1)</script>',
            kode: '123456',
            tautan: 'https://a.test',
        ))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $isi);
    }

    public function test_menyunting_template_asing_ditolak(): void
    {
        foreach (['tidak-ada', '../../app', 'layout'] as $jahat) {
            $this->actingAs($this->developer)
                ->patch('/panitia/pratinjau-email/'.urlencode($jahat), ['subject' => 'X', 'isi' => '<p>X</p>'])
                ->assertNotFound();
        }

        $this->assertSame(0, EmailTemplate::count());
    }

    public function test_panitia_biasa_tidak_bisa_menyunting_template(): void
    {
        $this->actingAs($this->panitia)
            ->patch('/panitia/pratinjau-email/verifikasi', ['subject' => 'X', 'isi' => '<p>X</p>'])
            ->assertForbidden();

        $this->actingAs($this->panitia)
            ->post('/panitia/pratinjau-email/verifikasi/draf', ['isi' => '<p>X</p>'])
            ->assertForbidden();

        $this->assertSame(0, EmailTemplate::count());
    }

    public function test_pratinjau_verifikasi_merender_kode_tombol_dan_tanda_tangan(): void
    {
        $respon = $this->actingAs($this->developer)->get('/panitia/pratinjau-email/verifikasi');

        $respon->assertOk();
        $respon->assertHeader('Content-Type', 'text/html; charset=utf-8');

        $isi = $respon->getContent();

        $this->assertStringContainsString('Verifikasi Email Saya', $isi);
        $this->assertStringContainsString('Kode Verifikasi', $isi);
        $this->assertStringContainsString('Ikatan Keluarga Alumni', $isi);
    }

    public function test_pratinjau_uji_coba_ikut_bisa_dirender(): void
    {
        $this->actingAs($this->developer)
            ->get('/panitia/pratinjau-email/uji-coba')
            ->assertOk()
            ->assertSee('Uji coba pengiriman email', false);
    }

    public function test_pratinjau_memakai_data_contoh_bukan_data_peserta(): void
    {
        // Halaman ini dibuka untuk memeriksa tata letak; tidak ada alasan
        // menampilkan alamat email peserta sungguhan hanya demi melihat rupanya.
        User::create([
            'name' => 'Peserta Asli', 'email' => 'rahasia-peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
        ]);

        $this->actingAs($this->developer)
            ->get('/panitia/pratinjau-email/verifikasi')
            ->assertDontSee('rahasia-peserta@example.com', false)
            ->assertSee('Rian Pelari', false);
    }

    public function test_nama_template_asing_ditolak(): void
    {
        // Nama template datang dari URL, jadi tidak boleh dipakai langsung untuk
        // memilih berkas view — termasuk upaya menembus ke direktori lain.
        foreach (['tidak-ada', '../../app', 'layout'] as $jahat) {
            $this->actingAs($this->developer)
                ->get('/panitia/pratinjau-email/'.urlencode($jahat))
                ->assertNotFound();
        }
    }

    public function test_pratinjau_email_tertutup_untuk_panitia_biasa(): void
    {
        $this->actingAs($this->panitia)->get('/panitia/pratinjau-email')->assertForbidden();
        $this->actingAs($this->panitia)->get('/panitia/pratinjau-email/verifikasi')->assertForbidden();
    }

    public function test_pratinjau_tidak_mengirim_email_apa_pun(): void
    {
        Mail::fake();

        $this->actingAs($this->developer)->get('/panitia/pratinjau-email/verifikasi')->assertOk();
        $this->actingAs($this->developer)->get('/panitia/pratinjau-email/uji-coba')->assertOk();

        Mail::assertNothingSent();
    }

    /* ---------------------------------------------------- Uji kirim WA */

    private function nyalakanWa(): void
    {
        Setting::simpan([
            'wa_enabled' => '1',
            'wa_api_url' => 'https://api.mpedia.test/send',
            'wa_api_key' => 'kunci-uji',
            'wa_sender' => '6281100000000',
        ]);
    }

    public function test_uji_wa_mengirim_pesan_ke_nomor_tujuan(): void
    {
        Http::fake(['*' => Http::response(['status' => true])]);
        $this->nyalakanWa();

        $this->actingAs($this->developer)
            ->post('/panitia/pengaturan/uji-wa', ['nomor' => '081298765432'])
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request['number'] === '6281298765432'
            && str_contains($request['message'], 'Uji coba gateway WhatsApp'));
    }

    public function test_uji_wa_ditolak_selama_gateway_belum_aktif(): void
    {
        Http::fake();

        $this->actingAs($this->developer)
            ->post('/panitia/pengaturan/uji-wa', ['nomor' => '081298765432'])
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_uji_wa_menolak_nomor_yang_tidak_masuk_akal(): void
    {
        Http::fake();
        $this->nyalakanWa();

        // Terlalu pendek untuk nomor Indonesia mana pun.
        $this->actingAs($this->developer)
            ->post('/panitia/pengaturan/uji-wa', ['nomor' => '0812'])
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_uji_wa_tertutup_untuk_panitia_biasa(): void
    {
        Http::fake();
        $this->nyalakanWa();

        $this->actingAs($this->panitia)
            ->post('/panitia/pengaturan/uji-wa', ['nomor' => '081298765432'])
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
