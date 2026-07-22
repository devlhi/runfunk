<?php

namespace Tests\Feature;

use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\User;
use App\Services\QrToken;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kode QR pada nomor BIB dan pemindaiannya oleh panitia.
 */
class QrPindaiTest extends TestCase
{
    use RefreshDatabase;

    private User $panitia;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);

        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
            'email_verified_at' => now(),
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => now(),
        ]);
    }

    private function pesertaLunas(string $bib = '5001'): Registration
    {
        return Registration::create([
            'registration_code' => 'GFR-5K-'.$bib,
            'user_id' => $this->peserta->id,
            'race_category_id' => RaceCategory::where('slug', '5k')->first()->id,
            'participant_name' => 'Rian Pelari',
            'participant_email' => 'rian@example.com',
            'participant_phone' => '0812',
            'gender' => 'L', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'amount' => 100000, 'status' => Registration::STATUS_CONFIRMED,
            'bib_number' => $bib,
        ]);
    }

    private function pindai(string $kode, string $jenis = 'racepack')
    {
        return $this->actingAs($this->panitia)
            ->postJson('/panitia/kehadiran/pindai/qr', ['kode' => $kode, 'jenis' => $jenis]);
    }

    /* ------------------------------------------------- Token & tanda tangan */

    public function test_kode_qr_tidak_memuat_nomor_bib_telanjang(): void
    {
        // Nomor BIB tercetak besar-besar untuk dilihat semua orang. Kalau isi QR
        // hanya nomor itu, siapa pun bisa mencetak kartu palsu dan mengambil
        // race pack orang lain.
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->assertNotSame('5001', $kode);
        $this->assertStringNotContainsString('5001', $kode);
        // Tanda tangannya membuat kodenya jauh lebih panjang dari sekadar id.
        $this->assertGreaterThan(12, strlen($kode));
    }

    public function test_kode_dengan_tanda_tangan_palsu_ditolak(): void
    {
        $daftar = $this->pesertaLunas();

        $this->pindai("P.{$daftar->id}.000000000000")
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertNull($daftar->fresh()->racepack_at);
    }

    public function test_kode_tanpa_tanda_tangan_ditolak(): void
    {
        $daftar = $this->pesertaLunas();

        foreach ([(string) $daftar->id, "P.{$daftar->id}", '5001', 'sembarang'] as $palsu) {
            $this->pindai($palsu)->assertStatus(422);
        }

        $this->assertNull($daftar->fresh()->racepack_at);
    }

    public function test_tanda_tangan_peserta_lain_tidak_bisa_dipakai_silang(): void
    {
        // Menukar id pada kode yang sah tidak boleh menandai orang lain.
        $satu = $this->pesertaLunas('5001');
        $dua = $this->pesertaLunas('5002');

        $kodeSatu = app(QrToken::class)->untukPeserta($satu);
        $bagian = explode('.', $kodeSatu);

        $this->pindai("P.{$dua->id}.{$bagian[2]}")->assertStatus(422);

        $this->assertNull($dua->fresh()->racepack_at);
    }

    /* ------------------------------------------------------- Jalur normal */

    public function test_pindai_menandai_race_pack(): void
    {
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->pindai($kode)
            ->assertOk()
            ->assertJson(['ok' => true, 'ulangan' => false])
            ->assertJsonPath('peserta.bib', '5001')
            ->assertJsonPath('peserta.nama', 'Rian Pelari');

        $daftar->refresh();

        $this->assertNotNull($daftar->racepack_at);
        $this->assertSame($this->panitia->id, $daftar->racepack_by);
        // Yang ditandai hanya yang diminta — kehadiran masih kosong.
        $this->assertNull($daftar->checkin_at);
    }

    public function test_pindai_bisa_menandai_kehadiran(): void
    {
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->pindai($kode, 'checkin')->assertOk();

        $this->assertNotNull($daftar->fresh()->checkin_at);
        $this->assertNull($daftar->fresh()->racepack_at);
    }

    public function test_pindai_ulang_diberi_tahu_bukan_dianggap_galat(): void
    {
        // Di antrean panjang, kartu yang sama terpindai dua kali itu wajar.
        // Panitia harus diberi tahu supaya tidak memberi race pack dua kali,
        // tapi bukan disodori pesan galat yang bikin ragu.
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->pindai($kode)->assertOk();
        $waktuPertama = $daftar->fresh()->racepack_at;

        $this->pindai($kode)
            ->assertOk()
            ->assertJson(['ok' => true, 'ulangan' => true]);

        // Waktu pengambilan pertama tidak boleh tertimpa.
        $this->assertEquals($waktuPertama, $daftar->fresh()->racepack_at);
    }

    public function test_penandaan_dikunci_supaya_tidak_terbagi_dua_kali(): void
    {
        // Dua panitia di dua meja memindai kartu yang sama nyaris bersamaan.
        // Tanpa kunci baris, keduanya melihat kolomnya masih kosong lalu
        // sama-sama membagikan race pack untuk satu orang.
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->pindai($kode)->assertJson(['ulangan' => false]);
        $this->pindai($kode)->assertJson(['ulangan' => true]);

        // Yang menandai tetap panitia pertama, dan waktunya tidak tertimpa.
        $this->assertSame($this->panitia->id, $daftar->fresh()->racepack_by);

        // Bukti kuncinya benar-benar dipasang, bukan sekadar cek berurutan yang
        // kebetulan lolos: kueri penandaan harus memakai SELECT ... FOR UPDATE.
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->pindai($kode);

        $adaKunci = collect(\Illuminate\Support\Facades\DB::getQueryLog())
            ->contains(fn ($q) => str_contains(strtolower($q['query']), 'for update'));

        $this->assertTrue($adaKunci, 'Penandaan dibaca tanpa mengunci barisnya.');
    }

    public function test_peserta_belum_lunas_tidak_bisa_dipindai(): void
    {
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $daftar->update(['status' => Registration::STATUS_PENDING_PAYMENT]);

        $this->pindai($kode)->assertStatus(422);
    }

    /* ------------------------------------------------------- Hak akses */

    public function test_peserta_tidak_bisa_memindai(): void
    {
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->actingAs($this->peserta)
            ->postJson('/panitia/kehadiran/pindai/qr', ['kode' => $kode, 'jenis' => 'racepack'])
            ->assertForbidden();

        $this->assertNull($daftar->fresh()->racepack_at);
    }

    public function test_tamu_tidak_bisa_memindai(): void
    {
        $daftar = $this->pesertaLunas();
        $kode = app(QrToken::class)->untukPeserta($daftar);

        $this->postJson('/panitia/kehadiran/pindai/qr', ['kode' => $kode, 'jenis' => 'racepack'])
            ->assertUnauthorized();

        $this->assertNull($daftar->fresh()->racepack_at);
    }

    /* --------------------------------------------------- QR di kartu BIB */

    public function test_lembar_cetak_membawa_qr_tiap_peserta(): void
    {
        $daftar = $this->pesertaLunas();

        $this->actingAs($this->panitia)
            ->get("/panitia/cetak-bib/lembar?ids={$daftar->id}")
            ->assertInertia(fn ($page) => $page
                ->where('registrations.0.qr', fn ($qr) => str_starts_with($qr, 'data:image/svg+xml;base64,')));
    }

    public function test_qr_satu_peserta_bisa_diambil_sebagai_gambar(): void
    {
        $daftar = $this->pesertaLunas();

        $respon = $this->actingAs($this->panitia)->get("/panitia/pendaftaran/{$daftar->id}/qr");

        $respon->assertOk();
        $respon->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $respon->getContent());
    }

    public function test_qr_peserta_tertutup_untuk_peserta(): void
    {
        $daftar = $this->pesertaLunas();

        $this->actingAs($this->peserta)
            ->get("/panitia/pendaftaran/{$daftar->id}/qr")
            ->assertForbidden();
    }

    public function test_peserta_tanpa_bib_tidak_punya_qr(): void
    {
        $daftar = $this->pesertaLunas();
        $daftar->update(['bib_number' => null]);

        $this->actingAs($this->panitia)
            ->get("/panitia/pendaftaran/{$daftar->id}/qr")
            ->assertNotFound();
    }
}
