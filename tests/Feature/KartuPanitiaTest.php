<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\QrToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu tanda panitia dan pemeriksaannya di lapangan.
 */
class KartuPanitiaTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $panitia;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->developer = User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
            'email_verified_at' => now(),
        ]);
        $this->panitia = User::create([
            'name' => 'Bendahara IKA', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
            'email_verified_at' => now(), 'phone' => '081234567890',
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => now(),
        ]);
    }

    private function periksa(string $kode, ?User $sebagai = null)
    {
        return $this->actingAs($sebagai ?? $this->panitia)
            ->postJson('/panitia/kartu-panitia/validasi', ['kode' => $kode]);
    }

    /* ------------------------------------------------------- Jalur normal */

    public function test_versi_kartu_terisi_sejak_akun_dibuat(): void
    {
        // Nilai bawaannya ada di basis data, tapi tidak ikut terbaca ke objek yang
        // baru dibuat. Tanda tangan QR dihitung dari angka ini, jadi kalau null,
        // kartu yang dicetak tepat setelah akunnya dibuat menghasilkan kode cacat.
        $baru = User::create([
            'name' => 'Panitia Baru', 'email' => 'baru@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);

        $this->assertSame(1, $baru->card_version);
        $this->periksa(app(QrToken::class)->untukPanitia($baru), $this->developer)
            ->assertJson(['sah' => true]);
    }

    public function test_kartu_sah_menampilkan_pemegangnya(): void
    {
        $kode = app(QrToken::class)->untukPanitia($this->panitia);

        $this->periksa($kode)
            ->assertOk()
            ->assertJson(['sah' => true])
            ->assertJsonPath('orang.nama', 'Bendahara IKA')
            ->assertJsonPath('orang.nomor', 'PAN-'.str_pad((string) $this->panitia->id, 3, '0', STR_PAD_LEFT).'-01');
    }

    public function test_jabatan_di_kartu_dipakai_kalau_diisi(): void
    {
        $this->panitia->update(['card_title' => 'Koordinator Race Pack']);

        $kode = app(QrToken::class)->untukPanitia($this->panitia->fresh());

        $this->periksa($kode)->assertJsonPath('orang.jabatan', 'Koordinator Race Pack');
    }

    /* --------------------------------------------------- Kartu palsu */

    public function test_kode_karangan_ditolak(): void
    {
        // Kartu yang dibuat sendiri dengan aplikasi QR biasa tidak boleh lolos.
        foreach ([
            "K.{$this->panitia->id}.1.000000000000",
            "K.{$this->panitia->id}.1",
            (string) $this->panitia->id,
            'panitia',
        ] as $palsu) {
            $this->periksa($palsu)->assertOk()->assertJson(['sah' => false]);
        }
    }

    public function test_kartu_peserta_tidak_bisa_dipakai_sebagai_kartu_panitia(): void
    {
        // Tipe kodenya berbeda; QR nomor BIB tidak boleh membuka pintu panitia.
        $daftar = \App\Models\Registration::create([
            'registration_code' => 'GFR-5K-0001',
            'user_id' => $this->peserta->id,
            'race_category_id' => \App\Models\RaceCategory::create([
                'name' => '5K', 'slug' => '5k', 'distance_label' => '5K',
                'price' => 100000, 'quota' => 10, 'bib_start' => 5000,
                'sort_order' => 1, 'is_active' => true,
            ])->id,
            'participant_name' => 'Rian', 'participant_email' => 'r@e.com',
            'participant_phone' => '0812', 'gender' => 'L',
            'birth_date' => '1996-08-17', 'city' => 'Gorontalo', 'jersey_size' => 'M',
            'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'amount' => 100000, 'status' => 'confirmed', 'bib_number' => '5001',
        ]);

        $kodePeserta = app(QrToken::class)->untukPeserta($daftar);

        $this->periksa($kodePeserta)->assertJson(['sah' => false]);
    }

    /* ------------------------------------------- Kartu hilang & pensiun */

    public function test_terbitkan_ulang_membuat_kartu_lama_ditolak(): void
    {
        $kodeLama = app(QrToken::class)->untukPanitia($this->panitia);

        $this->periksa($kodeLama)->assertJson(['sah' => true]);

        $this->actingAs($this->developer)
            ->post("/panitia/kartu-panitia/{$this->panitia->id}/terbitkan-ulang")
            ->assertSessionHasNoErrors();

        // Ini satu-satunya cara menonaktifkan kartu yang hilang tanpa
        // menghapus akun orangnya.
        $this->periksa($kodeLama)->assertJson(['sah' => false]);

        $kodeBaru = app(QrToken::class)->untukPanitia($this->panitia->fresh());
        $this->periksa($kodeBaru)->assertJson(['sah' => true]);
    }

    public function test_kartu_orang_yang_bukan_panitia_lagi_ditolak(): void
    {
        $kode = app(QrToken::class)->untukPanitia($this->panitia);

        $this->panitia->update(['role' => User::ROLE_PESERTA]);

        // Diperiksa oleh developer, bukan oleh akun yang barusan diturunkan —
        // kalau tidak, yang teruji jadi penjaga aksesnya, bukan kartunya.
        $this->periksa($kode, $this->developer)
            ->assertJson(['sah' => false])
            ->assertJsonFragment(['pesan' => 'Pemegang kartu ini sudah bukan panitia.']);
    }

    /* ------------------------------------------------------- Hak akses */

    public function test_semua_panitia_boleh_memeriksa_kartu(): void
    {
        // Yang berjaga di pintu masuk belum tentu developer — justru merekalah
        // yang paling perlu memeriksa.
        $this->actingAs($this->panitia)->get('/panitia/kartu-panitia/validasi')->assertOk();
    }

    public function test_hanya_developer_yang_bisa_menerbitkan_kartu(): void
    {
        // Kalau panitia mana pun bisa mencetak kartu, pemeriksaannya jadi tak berarti.
        $this->actingAs($this->panitia)->get('/panitia/kartu-panitia')->assertForbidden();
        $this->actingAs($this->panitia)
            ->post("/panitia/kartu-panitia/{$this->panitia->id}/terbitkan-ulang")
            ->assertForbidden();

        $this->assertSame(1, $this->panitia->fresh()->card_version);
    }

    public function test_peserta_tidak_bisa_menyentuh_kartu_panitia(): void
    {
        $this->actingAs($this->peserta)->get('/panitia/kartu-panitia')->assertForbidden();
        $this->actingAs($this->peserta)->get('/panitia/kartu-panitia/validasi')->assertForbidden();
        $this->actingAs($this->peserta)
            ->postJson('/panitia/kartu-panitia/validasi', ['kode' => 'apa saja'])
            ->assertForbidden();
    }

    public function test_akun_peserta_tidak_bisa_dibuatkan_kartu(): void
    {
        $this->actingAs($this->developer)
            ->post("/panitia/kartu-panitia/{$this->peserta->id}/terbitkan-ulang")
            ->assertNotFound();
    }

    /* --------------------------------------------------- Lembar cetak */

    public function test_lembar_cetak_membawa_qr_tiap_kartu(): void
    {
        $this->actingAs($this->developer)
            ->get("/panitia/kartu-panitia/lembar?ids={$this->panitia->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/CardSheet')
                ->has('kartu', 1)
                ->where('kartu.0.nama', 'Bendahara IKA')
                ->where('kartu.0.qr', fn ($qr) => str_starts_with($qr, 'data:image/svg+xml;base64,')));
    }

    public function test_daftar_kartu_tidak_membawa_qr(): void
    {
        // Data URI untuk tiap orang hanya membengkakkan muatan halaman daftar;
        // kodenya baru dibutuhkan saat benar-benar dicetak.
        $this->actingAs($this->developer)
            ->get('/panitia/kartu-panitia')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('pengelola.0.qr'));
    }

    public function test_akun_peserta_tidak_ikut_di_lembar_cetak(): void
    {
        $this->actingAs($this->developer)
            ->get("/panitia/kartu-panitia/lembar?ids={$this->peserta->id},{$this->panitia->id}")
            ->assertInertia(fn ($page) => $page
                ->has('kartu', 1)
                ->where('kartu.0.nama', 'Bendahara IKA'));
    }

    public function test_developer_bisa_menyimpan_jabatan(): void
    {
        $this->actingAs($this->developer)
            ->patch("/panitia/kartu-panitia/{$this->panitia->id}/jabatan", ['card_title' => 'Tim Medis'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Tim Medis', $this->panitia->fresh()->card_title);
    }
}
