<?php

namespace Tests\Feature;

use App\Jobs\KirimPengumuman;
use App\Models\Announcement;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PengumumanPanitia;
use App\Services\WhatsAppGateway;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $panitia;

    private User $developer;

    private User $peserta;

    private Announcement $pengumuman;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);

        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);
        $this->developer = User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);

        $this->pengumuman = Announcement::create([
            'title' => 'Race pack H-2', 'body' => 'Kamis 09.00-17.00.',
            'level' => 'penting', 'is_published' => true, 'created_by' => $this->panitia->id,
        ]);
    }

    private function buatPendaftaran(string $telepon = '081234567890'): Registration
    {
        return Registration::create([
            'registration_code' => 'GFR-'.fake()->unique()->numerify('####'),
            'user_id' => $this->peserta->id,
            'race_category_id' => RaceCategory::first()->id,
            'participant_name' => 'Pelari', 'participant_email' => 'a@b.com',
            'participant_phone' => $telepon,
            'gender' => 'L', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'amount' => 100000, 'status' => Registration::STATUS_CONFIRMED,
        ]);
    }

    private function aktifkanGateway(): void
    {
        Setting::simpan([
            'wa_enabled' => '1',
            'wa_api_url' => 'https://mpedia.test/send_message',
            'wa_api_key' => 'kunci-rahasia',
            'wa_sender' => '628111',
        ]);
    }

    /* --------------------------------------------- Normalisasi nomor */

    public function test_nomor_whatsapp_dinormalkan_ke_format_62(): void
    {
        $wa = app(WhatsAppGateway::class);

        $this->assertSame('6281234567890', $wa->normalkanNomor('081234567890'));
        $this->assertSame('6281234567890', $wa->normalkanNomor('+62 812-3456-7890'));
        $this->assertSame('6281234567890', $wa->normalkanNomor('6281234567890'));
        $this->assertSame('6281234567890', $wa->normalkanNomor('81234567890'));

        // Yang tidak masuk akal ditolak, bukan dikirim dan gagal di gateway.
        $this->assertNull($wa->normalkanNomor('123'));
        $this->assertNull($wa->normalkanNomor(''));
        $this->assertNull($wa->normalkanNomor(null));
    }

    /* ------------------------------------------------- Sakelar gateway */

    public function test_gateway_mati_kalau_belum_dikonfigurasi(): void
    {
        $this->assertFalse(app(WhatsAppGateway::class)->aktif());

        $hasil = app(WhatsAppGateway::class)->kirim('081234567890', 'Halo');

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum diaktifkan', $hasil['pesan']);
    }

    public function test_gateway_mengirim_ke_endpoint_mpedia(): void
    {
        Http::fake(['mpedia.test/*' => Http::response(['status' => true], 200)]);
        $this->aktifkanGateway();

        $hasil = app(WhatsAppGateway::class)->kirim('081234567890', 'Halo');

        $this->assertTrue($hasil['ok']);

        Http::assertSent(fn ($request) => $request['api_key'] === 'kunci-rahasia'
            && $request['number'] === '6281234567890'
            && $request['message'] === 'Halo');
    }

    public function test_gateway_yang_menolak_tidak_membuat_aplikasi_gagal(): void
    {
        Http::fake(['mpedia.test/*' => Http::response('Unauthorized', 401)]);
        $this->aktifkanGateway();

        $hasil = app(WhatsAppGateway::class)->kirim('081234567890', 'Halo');

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('401', $hasil['pesan']);
    }

    /* --------------------------------------------------- Uji kirim */

    public function test_panitia_bisa_uji_kirim_ke_satu_nomor(): void
    {
        Http::fake(['mpedia.test/*' => Http::response(['status' => true], 200)]);
        $this->aktifkanGateway();

        $this->actingAs($this->panitia)
            ->post("/panitia/pengumuman/{$this->pengumuman->id}/uji", ['nomor' => '081234567890'])
            ->assertRedirect();

        Http::assertSentCount(1);
    }

    /* ---------------------------------------------------- Broadcast */

    public function test_broadcast_mengantre_pekerjaan_bukan_mengirim_langsung(): void
    {
        Queue::fake();
        $this->buatPendaftaran();

        $this->actingAs($this->panitia)
            ->post("/panitia/pengumuman/{$this->pengumuman->id}/kirim", ['email' => true, 'whatsapp' => false])
            ->assertRedirect();

        // Kirim ribuan pesan tidak boleh dilakukan di dalam satu request HTTP.
        Queue::assertPushed(KirimPengumuman::class);
    }

    public function test_broadcast_mencatat_waktu_pengirimannya(): void
    {
        Queue::fake();
        $this->buatPendaftaran();

        $this->actingAs($this->panitia)
            ->post("/panitia/pengumuman/{$this->pengumuman->id}/kirim", ['email' => true, 'whatsapp' => false]);

        $this->assertNotNull($this->pengumuman->fresh()->broadcast_at);
    }

    public function test_pengumuman_draf_tidak_bisa_disiarkan(): void
    {
        Queue::fake();
        $this->buatPendaftaran();
        $this->pengumuman->update(['is_published' => false]);

        $this->actingAs($this->panitia)
            ->post("/panitia/pengumuman/{$this->pengumuman->id}/kirim", ['email' => true, 'whatsapp' => false]);

        Queue::assertNothingPushed();
    }

    public function test_broadcast_tanpa_saluran_ditolak(): void
    {
        Queue::fake();
        $this->buatPendaftaran();

        $this->actingAs($this->panitia)
            ->post("/panitia/pengumuman/{$this->pengumuman->id}/kirim", ['email' => false, 'whatsapp' => false]);

        Queue::assertNothingPushed();
    }

    public function test_peserta_tidak_bisa_menyiarkan_pengumuman(): void
    {
        Queue::fake();

        $this->actingAs($this->peserta)
            ->post("/panitia/pengumuman/{$this->pengumuman->id}/kirim", ['email' => true, 'whatsapp' => false])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_pekerjaan_broadcast_mengirim_email_ke_peserta(): void
    {
        Notification::fake();
        $this->buatPendaftaran();

        (new KirimPengumuman($this->pengumuman->id, true, false))
            ->handle(app(WhatsAppGateway::class));

        Notification::assertSentTo($this->peserta, PengumumanPanitia::class);
    }

    public function test_pendaftaran_batal_tidak_ikut_dikirimi(): void
    {
        Notification::fake();
        $batal = $this->buatPendaftaran();
        $batal->update(['status' => Registration::STATUS_CANCELLED]);

        (new KirimPengumuman($this->pengumuman->id, true, false))
            ->handle(app(WhatsAppGateway::class));

        Notification::assertNothingSent();
    }

    /* --------------------------------------------- Kerahasiaan API key */

    public function test_api_key_tidak_ikut_terkirim_ke_browser(): void
    {
        $this->aktifkanGateway();

        $this->actingAs($this->developer)
            ->get('/panitia/pengaturan')
            ->assertInertia(fn ($page) => $page
                ->where('settings.wa_api_key', '')
                ->where('settings.wa_api_key_terisi', true))
            ->assertDontSee('kunci-rahasia');
    }

    public function test_mengosongkan_api_key_tidak_menghapus_yang_tersimpan(): void
    {
        $this->aktifkanGateway();

        $this->actingAs($this->developer)->patch('/panitia/pengaturan', [
            'event_name' => 'Gong Fun Run 2026',
            'event_date' => '2026-10-31T06:00:00',
            'location' => 'Lapangan Tuladenggi',
            'payment_bank' => 'BRI', 'payment_account' => '123', 'payment_holder' => 'Panitia',
            'payment_whatsapp' => '0812', 'payment_deadline_hours' => 24,
            'registration_open' => true,
            'wa_enabled' => true,
            'wa_api_url' => 'https://mpedia.test/send_message',
            'wa_api_key' => '',            // dikosongkan = jangan diubah
            'wa_sender' => '628111',
        ])->assertRedirect();

        $this->assertSame('kunci-rahasia', Setting::ambil('wa_api_key'));
    }
}
