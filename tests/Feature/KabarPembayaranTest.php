<?php

namespace Tests\Feature;

use App\Jobs\KirimKabarPembayaran;
use App\Models\News;
use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\KabarPembayaran;
use App\Services\WhatsAppGateway;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KabarPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private User $panitia;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RaceCategorySeeder::class);

        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
    }

    private function siapkanBukti(): Payment
    {
        $category = RaceCategory::where('slug', '5k')->first();

        $this->actingAs($this->peserta)->post('/pendaftaran', [
            'race_category_id' => $category->id,
            'participant_name' => 'Rian Pelari',
            'participant_email' => 'rian@example.com',
            'participant_phone' => '081234567890',
            'gender' => 'L', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'agreement' => true,
        ]);

        $registrasi = Registration::latest('id')->first();

        $this->actingAs($this->peserta)->post("/pendaftaran/{$registrasi->id}/pembayaran", [
            'method' => 'transfer', 'sender_name' => 'Rian',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        return Payment::latest('id')->first();
    }

    /* ------------------------------------------------------- Pemicunya */

    public function test_menyetujui_pembayaran_mengantre_kabar_ke_peserta(): void
    {
        Queue::fake();
        $bukti = $this->siapkanBukti();

        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$bukti->id}/setujui");

        Queue::assertPushed(KirimKabarPembayaran::class);
    }

    public function test_menolak_bukti_juga_mengantre_kabar(): void
    {
        Queue::fake();
        $bukti = $this->siapkanBukti();

        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$bukti->id}/tolak", [
            'reason' => 'Nominal kurang Rp 5.000',
        ]);

        Queue::assertPushed(KirimKabarPembayaran::class);
    }

    public function test_penerbitan_bib_tetap_berhasil_walau_gateway_mati(): void
    {
        // Gateway tidak dikonfigurasi sama sekali — verifikasi tidak boleh ikut gagal.
        $bukti = $this->siapkanBukti();

        $this->actingAs($this->panitia)
            ->post("/panitia/pembayaran/{$bukti->id}/setujui")
            ->assertRedirect();

        $registrasi = $bukti->registration->fresh();

        $this->assertSame(Registration::STATUS_CONFIRMED, $registrasi->status);
        $this->assertNotNull($registrasi->bib_number);
    }

    /* --------------------------------------------------- Isi kabarnya */

    public function test_kabar_disetujui_memuat_nomor_bib(): void
    {
        Notification::fake();
        $bukti = $this->siapkanBukti();
        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$bukti->id}/setujui");

        $registrasi = $bukti->registration->fresh();

        (new KirimKabarPembayaran($registrasi->id, true))->handle(app(WhatsAppGateway::class));

        Notification::assertSentTo(
            $this->peserta,
            KabarPembayaran::class,
            function (KabarPembayaran $n) use ($registrasi) {
                $isi = $n->toMail($this->peserta)->render();

                return str_contains($isi, $registrasi->bib_number);
            }
        );
    }

    public function test_kabar_ditolak_memuat_alasannya(): void
    {
        Notification::fake();
        $bukti = $this->siapkanBukti();
        $alasan = 'Nominal kurang Rp 5.000';

        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$bukti->id}/tolak", ['reason' => $alasan]);

        (new KirimKabarPembayaran($bukti->registration_id, false, $alasan))
            ->handle(app(WhatsAppGateway::class));

        Notification::assertSentTo(
            $this->peserta,
            KabarPembayaran::class,
            fn (KabarPembayaran $n) => str_contains($n->toMail($this->peserta)->render(), $alasan)
        );
    }

    public function test_kabar_juga_dikirim_lewat_whatsapp_kalau_gateway_aktif(): void
    {
        Notification::fake();
        Http::fake(['mpedia.test/*' => Http::response(['status' => true], 200)]);

        Setting::simpan([
            'wa_enabled' => '1',
            'wa_api_url' => 'https://mpedia.test/send_message',
            'wa_api_key' => 'kunci',
            'wa_sender' => '628111',
        ]);

        $bukti = $this->siapkanBukti();
        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$bukti->id}/setujui");
        $registrasi = $bukti->registration->fresh();

        (new KirimKabarPembayaran($registrasi->id, true))->handle(app(WhatsAppGateway::class));

        Http::assertSent(fn ($request) => $request['number'] === '6281234567890'
            && str_contains($request['message'], $registrasi->bib_number));
    }

    /* ------------------------------------------ Komentar di lonceng */

    public function test_komentar_baru_ikut_muncul_di_lonceng_panitia(): void
    {
        $berita = News::create([
            'title' => 'Kabar', 'slug' => 'kabar', 'body' => 'Isi',
            'is_published' => true, 'published_at' => now()->subHour(),
            'created_by' => $this->panitia->id,
        ]);

        $berita->comments()->create(['user_id' => $this->peserta->id, 'body' => 'Komentar spam']);

        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($p) => $p
                ->where('panitia.feed.0.jenis', 'komentar')
                ->where('panitia.feed.0.name', 'Peserta')
                ->where('panitia.unread', 1));
    }

    public function test_lonceng_menggabung_pendaftaran_dan_komentar_urut_waktu(): void
    {
        $berita = News::create([
            'title' => 'Kabar', 'slug' => 'kabar', 'body' => 'Isi',
            'is_published' => true, 'published_at' => now()->subHour(),
            'created_by' => $this->panitia->id,
        ]);

        $berita->comments()->create(['user_id' => $this->peserta->id, 'body' => 'Komentar lama']);

        $this->travel(5)->minutes();
        $this->siapkanBukti();   // pendaftaran lebih baru

        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($p) => $p
                ->has('panitia.feed', 2)
                // Yang paling baru di atas, apa pun jenisnya.
                ->where('panitia.feed.0.jenis', 'pendaftaran')
                ->where('panitia.feed.1.jenis', 'komentar')
                ->where('panitia.unread', 2));
    }

    public function test_peserta_tidak_menerima_data_lonceng(): void
    {
        $this->actingAs($this->peserta)
            ->get('/dashboard')
            ->assertInertia(fn ($p) => $p->where('panitia', null));
    }
}
