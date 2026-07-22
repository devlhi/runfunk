<?php

namespace Tests\Feature;

use App\Jobs\KirimKabarPembayaran;
use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Konfirmasi pembayaran yang tidak punya bukti unggahan — peserta membayar tunai
 * ke panitia, atau dananya terlihat di mutasi rekening tanpa peserta pernah
 * membuka situs.
 */
class KonfirmasiManualTest extends TestCase
{
    use RefreshDatabase;

    private User $panitia;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);

        $this->panitia = User::create([
            'name' => 'Bendahara IKA', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
    }

    /** Pendaftaran yang menunggu pembayaran, tanpa bukti apa pun. */
    private function pendaftaranTanpaBukti(): Registration
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

        return Registration::latest('id')->first();
    }

    private function konfirmasi(Registration $registrasi, array $ubah = [])
    {
        return $this->actingAs($this->panitia)->post(
            "/panitia/pendaftaran/{$registrasi->id}/konfirmasi-manual",
            array_merge([
                'metode' => 'tunai',
                'catatan' => 'Bayar tunai di sekretariat IKA, kuitansi no. 014.',
            ], $ubah)
        );
    }

    /* ------------------------------------------------------- Jalur utama */

    public function test_konfirmasi_manual_menerbitkan_bib_tanpa_bukti(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();

        $this->konfirmasi($registrasi)->assertRedirect();

        $registrasi->refresh();

        $this->assertSame(Registration::STATUS_CONFIRMED, $registrasi->status);
        $this->assertNotNull($registrasi->bib_number);
        $this->assertStringStartsWith('5', (string) $registrasi->bib_number);
    }

    public function test_pembayarannya_tercatat_lengkap_dengan_catatan_dan_pemeriksa(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();

        $this->konfirmasi($registrasi, ['catatan' => 'Tunai diterima bendahara 12 Okt.']);

        $bayar = Payment::latest('id')->first();

        $this->assertSame('tunai', $bayar->method);
        $this->assertNull($bayar->proof_path);
        $this->assertTrue($bayar->tanpaBukti());
        $this->assertSame(Payment::STATUS_APPROVED, $bayar->status);
        $this->assertSame('Tunai diterima bendahara 12 Okt.', $bayar->confirm_note);
        // Jejak siapa yang mengakui uangnya masuk — satu-satunya bukti yang tersisa.
        $this->assertSame($this->panitia->id, $bayar->reviewed_by);
        $this->assertEquals($registrasi->amount, $bayar->amount);
    }

    public function test_peserta_tetap_dikabari(): void
    {
        Queue::fake();

        $this->konfirmasi($this->pendaftaranTanpaBukti());

        Queue::assertPushed(KirimKabarPembayaran::class);
    }

    /* ------------------------------------------------ Laporan ke admin WA */

    public function test_laporan_dikirim_ke_whatsapp_admin(): void
    {
        Http::fake(['*' => Http::response(['status' => true])]);

        Setting::simpan([
            'payment_whatsapp' => '081298765432',
            'wa_enabled' => '1',
            'wa_api_url' => 'https://api.mpedia.test/send',
            'wa_api_key' => 'kunci-uji',
            'wa_sender' => '6281100000000',
        ]);

        $registrasi = $this->pendaftaranTanpaBukti();
        $this->konfirmasi($registrasi, ['catatan' => 'Tunai di sekretariat, kuitansi 014.']);

        $bib = $registrasi->fresh()->bib_number;

        Http::assertSent(function ($request) use ($bib) {
            $pesan = $request['message'] ?? '';

            return $request['number'] === '6281298765432'
                && str_contains($pesan, 'TANPA bukti')
                && str_contains($pesan, 'Rian Pelari')
                && str_contains($pesan, (string) $bib)
                && str_contains($pesan, 'kuitansi 014')
                // Panitia yang mengonfirmasi ikut dilaporkan, bukan cuma nominalnya.
                && str_contains($pesan, 'Bendahara IKA');
        });
    }

    public function test_konfirmasi_tetap_sah_walau_gateway_whatsapp_mati(): void
    {
        // Gateway sengaja tidak dikonfigurasi. Uang sudah diterima panitia di dunia
        // nyata — kegagalan mengirim laporan tidak boleh membatalkan penerbitan BIB.
        Setting::simpan(['payment_whatsapp' => '081298765432']);

        $registrasi = $this->pendaftaranTanpaBukti();

        $this->konfirmasi($registrasi)->assertRedirect();

        $this->assertSame(Registration::STATUS_CONFIRMED, $registrasi->fresh()->status);
        $this->assertNotNull($registrasi->fresh()->bib_number);
    }

    /* ------------------------------------------------------- Penjagaannya */

    public function test_catatan_wajib_diisi(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();

        $this->konfirmasi($registrasi, ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registrasi->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_metode_di_luar_daftar_ditolak(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();

        // 'transfer' hanya sah lewat unggahan bukti oleh peserta, bukan lewat jalur ini.
        $this->konfirmasi($registrasi, ['metode' => 'transfer'])
            ->assertSessionHasErrors('metode');

        $this->assertSame(0, Payment::count());
    }

    public function test_pendaftaran_yang_sudah_lunas_tidak_bisa_dikonfirmasi_lagi(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();
        $this->konfirmasi($registrasi);

        $bibAwal = $registrasi->fresh()->bib_number;

        $this->konfirmasi($registrasi)->assertSessionHas('error');

        // BIB tidak boleh bergeser dan pembayarannya tidak boleh tercatat dua kali.
        $this->assertSame($bibAwal, $registrasi->fresh()->bib_number);
        $this->assertSame(1, Payment::count());
    }

    public function test_pendaftaran_yang_sudah_dibatalkan_tidak_bisa_dikonfirmasi(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();
        $registrasi->update(['status' => Registration::STATUS_CANCELLED]);

        $this->konfirmasi($registrasi)->assertSessionHas('error');

        $this->assertSame(Registration::STATUS_CANCELLED, $registrasi->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_peserta_tidak_boleh_mengonfirmasi_pembayarannya_sendiri(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();

        $this->actingAs($this->peserta)
            ->post("/panitia/pendaftaran/{$registrasi->id}/konfirmasi-manual", [
                'metode' => 'tunai',
                'catatan' => 'Sudah saya bayar sendiri kok.',
            ])
            ->assertForbidden();

        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registrasi->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $registrasi = $this->pendaftaranTanpaBukti();

        // Persiapannya berjalan sebagai peserta; tanpa ini permintaan di bawah
        // masih terautentikasi dan yang teruji jadi middleware panitia, bukan auth.
        auth()->logout();

        $this->post("/panitia/pendaftaran/{$registrasi->id}/konfirmasi-manual", [
            'metode' => 'tunai', 'catatan' => 'Percobaan dari luar.',
        ])->assertRedirect('/masuk');

        $this->assertSame(0, Payment::count());
    }
}
