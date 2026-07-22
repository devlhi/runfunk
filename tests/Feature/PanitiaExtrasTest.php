<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rekap & laporan, race pack + kehadiran, dan pengumuman.
 */
class PanitiaExtrasTest extends TestCase
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
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
    }

    private function buatPendaftaran(array $override = []): Registration
    {
        $category = RaceCategory::where('slug', $override['slug'] ?? '5k')->first();

        return Registration::create([
            'registration_code' => 'GFR-'.fake()->unique()->numerify('####'),
            'user_id' => $this->peserta->id,
            'race_category_id' => $category->id,
            'participant_name' => $override['nama'] ?? 'Pelari Uji',
            'participant_email' => 'a@b.com',
            'participant_phone' => '0812',
            'gender' => $override['gender'] ?? 'L',
            'birth_date' => $override['lahir'] ?? '1996-08-17',
            'city' => $override['kota'] ?? 'Gorontalo',
            'jersey_size' => $override['jersey'] ?? 'M',
            'blood_type' => $override['darah'] ?? 'O',
            'community' => $override['komunitas'] ?? null,
            'emergency_name' => 'Ibu',
            'emergency_phone' => '0813',
            'amount' => $category->price,
            'status' => $override['status'] ?? Registration::STATUS_CONFIRMED,
            'bib_number' => $override['bib'] ?? null,
        ]);
    }

    /* ------------------------------------------------------ Rekap & laporan */

    public function test_rekap_jersey_dipisah_per_kategori(): void
    {
        $this->buatPendaftaran(['slug' => '5k', 'jersey' => 'M']);
        $this->buatPendaftaran(['slug' => '5k', 'jersey' => 'M']);
        $this->buatPendaftaran(['slug' => '5k', 'jersey' => 'L']);
        $this->buatPendaftaran(['slug' => '10k', 'jersey' => 'XL']);

        $this->actingAs($this->panitia)
            ->get('/panitia/laporan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/Reports')
                ->where('total', 4)
                ->has('jersey', 2)
                // 5K: M ada 2 buah
                ->where('jersey.0.kategori', '5K')
                ->where('jersey.0.total', 3)
                ->where('jersey.0.ukuran.1.label', 'M')
                ->where('jersey.0.ukuran.1.jumlah', 2));
    }

    public function test_rekap_bawaannya_hanya_menghitung_yang_lunas(): void
    {
        $this->buatPendaftaran(['status' => Registration::STATUS_CONFIRMED]);
        $this->buatPendaftaran(['status' => Registration::STATUS_PENDING_PAYMENT]);
        $this->buatPendaftaran(['status' => Registration::STATUS_WAITING_VERIFICATION]);

        // Bawaan: hanya lunas — angka yang aman dipakai memesan jersey.
        $this->actingAs($this->panitia)
            ->get('/panitia/laporan')
            ->assertInertia(fn ($page) => $page->where('total', 1));

        // Cakupan "aktif" ikut menghitung yang menunggu verifikasi.
        $this->actingAs($this->panitia)
            ->get('/panitia/laporan?cakupan=aktif')
            ->assertInertia(fn ($page) => $page->where('total', 2));
    }

    public function test_rekap_mengelompokkan_usia_dan_kota(): void
    {
        $this->buatPendaftaran(['lahir' => now()->subYears(20)->toDateString(), 'kota' => 'Limboto']);
        $this->buatPendaftaran(['lahir' => now()->subYears(30)->toDateString(), 'kota' => 'Limboto']);
        $this->buatPendaftaran(['lahir' => now()->subYears(60)->toDateString(), 'kota' => 'Gorontalo']);

        $this->actingAs($this->panitia)
            ->get('/panitia/laporan')
            ->assertInertia(fn ($page) => $page
                ->where('usia.1.label', '18–25')
                ->where('usia.1.jumlah', 1)
                ->where('usia.5.label', 'Di atas 55')
                ->where('usia.5.jumlah', 1)
                ->where('kota.0.label', 'Limboto')
                ->where('kota.0.jumlah', 2));
    }

    public function test_peserta_tidak_bisa_melihat_laporan(): void
    {
        $this->actingAs($this->peserta)->get('/panitia/laporan')->assertForbidden();
    }

    /* ------------------------------------------- Race pack & kehadiran */

    public function test_panitia_bisa_menandai_race_pack_dan_kehadiran(): void
    {
        $r = $this->buatPendaftaran(['bib' => '5001']);

        $this->actingAs($this->panitia)
            ->post("/panitia/kehadiran/{$r->id}", ['jenis' => 'racepack', 'nilai' => true])
            ->assertRedirect();

        $this->assertNotNull($r->fresh()->racepack_at);
        $this->assertSame($this->panitia->id, $r->fresh()->racepack_by);

        $this->actingAs($this->panitia)
            ->post("/panitia/kehadiran/{$r->id}", ['jenis' => 'checkin', 'nilai' => true]);

        $this->assertNotNull($r->fresh()->checkin_at);
    }

    public function test_penandaan_bisa_dibatalkan_kalau_salah_centang(): void
    {
        $r = $this->buatPendaftaran(['bib' => '5001']);

        $this->actingAs($this->panitia)->post("/panitia/kehadiran/{$r->id}", ['jenis' => 'racepack', 'nilai' => true]);
        $this->assertNotNull($r->fresh()->racepack_at);

        $this->actingAs($this->panitia)->post("/panitia/kehadiran/{$r->id}", ['jenis' => 'racepack', 'nilai' => false]);

        $this->assertNull($r->fresh()->racepack_at);
        $this->assertNull($r->fresh()->racepack_by);
    }

    public function test_pendaftaran_belum_lunas_tidak_bisa_ditandai(): void
    {
        $r = $this->buatPendaftaran(['status' => Registration::STATUS_PENDING_PAYMENT]);

        $this->actingAs($this->panitia)
            ->post("/panitia/kehadiran/{$r->id}", ['jenis' => 'racepack', 'nilai' => true])
            ->assertStatus(422);

        $this->assertNull($r->fresh()->racepack_at);
    }

    public function test_pencarian_kehadiran_bisa_lewat_nomor_bib(): void
    {
        $this->buatPendaftaran(['bib' => '5001', 'nama' => 'Rian Pelari']);
        $this->buatPendaftaran(['bib' => '5002', 'nama' => 'Sitti Rahma']);

        $this->actingAs($this->panitia)
            ->get('/panitia/kehadiran?cari=5002')
            ->assertInertia(fn ($page) => $page
                ->has('peserta.data', 1)
                ->where('peserta.data.0.nama', 'Sitti Rahma'));
    }

    public function test_saringan_belum_ambil_race_pack_bekerja(): void
    {
        $sudah = $this->buatPendaftaran(['bib' => '5001']);
        $this->buatPendaftaran(['bib' => '5002']);

        $this->actingAs($this->panitia)->post("/panitia/kehadiran/{$sudah->id}", ['jenis' => 'racepack', 'nilai' => true]);

        $this->actingAs($this->panitia)
            ->get('/panitia/kehadiran?saring=belum_racepack')
            ->assertInertia(fn ($page) => $page
                ->has('peserta.data', 1)
                ->where('peserta.data.0.bib', '5002')
                ->where('stats.racepack', 1));
    }

    public function test_peserta_tidak_bisa_menandai_kehadiran(): void
    {
        $r = $this->buatPendaftaran(['bib' => '5001']);

        $this->actingAs($this->peserta)
            ->post("/panitia/kehadiran/{$r->id}", ['jenis' => 'racepack', 'nilai' => true])
            ->assertForbidden();
    }

    /* ------------------------------------------------------- Pengumuman */

    public function test_panitia_bisa_menerbitkan_pengumuman(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/pengumuman', [
            'title' => 'Pengambilan race pack',
            'body' => 'Kamis 29 Oktober, 09.00-17.00 di Lapangan Tuladenggi.',
            'level' => Announcement::LEVEL_PENTING,
            'is_published' => true,
        ])->assertRedirect();

        $a = Announcement::first();

        $this->assertSame('Pengambilan race pack', $a->title);
        $this->assertSame($this->panitia->id, $a->created_by);
    }

    public function test_pengumuman_tayang_muncul_di_dashboard_peserta(): void
    {
        Announcement::create([
            'title' => 'Tayang', 'body' => 'Isi', 'level' => 'info',
            'is_published' => true, 'created_by' => $this->panitia->id,
        ]);
        Announcement::create([
            'title' => 'Draf', 'body' => 'Isi', 'level' => 'info',
            'is_published' => false, 'created_by' => $this->panitia->id,
        ]);

        $this->actingAs($this->peserta)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                // Draf tidak boleh ikut terkirim ke peserta.
                ->has('announcements', 1)
                ->where('announcements.0.title', 'Tayang'));
    }

    public function test_peserta_tidak_bisa_menulis_pengumuman(): void
    {
        $this->actingAs($this->peserta)
            ->post('/panitia/pengumuman', [
                'title' => 'Palsu', 'body' => 'Isi', 'level' => 'info', 'is_published' => true,
            ])
            ->assertForbidden();

        $this->assertSame(0, Announcement::count());
    }

    public function test_pengumuman_bisa_diubah_dan_dihapus(): void
    {
        $a = Announcement::create([
            'title' => 'Awal', 'body' => 'Isi', 'level' => 'info',
            'is_published' => true, 'created_by' => $this->panitia->id,
        ]);

        $this->actingAs($this->panitia)->patch("/panitia/pengumuman/{$a->id}", [
            'title' => 'Diubah', 'body' => 'Isi baru',
            'level' => Announcement::LEVEL_PENTING, 'is_published' => false,
        ])->assertRedirect();

        $this->assertSame('Diubah', $a->fresh()->title);
        $this->assertFalse($a->fresh()->is_published);

        $this->actingAs($this->panitia)->delete("/panitia/pengumuman/{$a->id}")->assertRedirect();
        $this->assertSame(0, Announcement::count());
    }
}
