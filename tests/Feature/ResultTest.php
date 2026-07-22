<?php

namespace Tests\Feature;

use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\User;
use App\Services\ResultService;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultTest extends TestCase
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

    private function buatPeserta(array $o = []): Registration
    {
        static $n = 0;
        $n++;

        return Registration::create([
            'registration_code' => 'GFR-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'user_id' => $o['user_id'] ?? $this->peserta->id,
            'race_category_id' => RaceCategory::where('slug', $o['slug'] ?? '5k')->first()->id,
            'participant_name' => $o['nama'] ?? "Pelari {$n}",
            'participant_email' => "p{$n}@example.com",
            'participant_phone' => '0812',
            'gender' => $o['gender'] ?? 'L',
            'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'amount' => 100000,
            'status' => $o['status'] ?? Registration::STATUS_CONFIRMED,
            'bib_number' => $o['bib'] ?? (string) (5000 + $n),
        ]);
    }

    /* --------------------------------------------- Penguraian waktu */

    public function test_waktu_diurai_ke_detik_dengan_benar(): void
    {
        $s = app(ResultService::class);

        $this->assertSame(3723, $s->keDetik('01:02:03'));     // jam:menit:detik
        $this->assertSame(1823, $s->keDetik('30:23'));        // menit:detik
        $this->assertSame(3600, $s->keDetik('1:00:00'));

        // Format yang tidak masuk akal ditolak, bukan diterjemahkan asal.
        $this->assertNull($s->keDetik('abc'));
        $this->assertNull($s->keDetik('1:2:3:4'));
        $this->assertNull($s->keDetik('01:75:00'));           // menit > 59 di bentuk 3 bagian
        $this->assertNull($s->keDetik('10:75'));              // detik > 59
        $this->assertNull($s->keDetik(''));
    }

    public function test_durasi_di_atas_satu_jam_bisa_ditulis_sebagai_menit(): void
    {
        $s = app(ResultService::class);

        // Menit pada bentuk dua bagian adalah DURASI, bukan pecahan jam. Aturan
        // lama menolak apa pun di atas 59 menit, sehingga peserta 10K yang finis
        // 90 menit — sepenuhnya wajar untuk fun run — tidak bisa dicatat sama sekali.
        $this->assertSame(5400, $s->keDetik('90:00'));
        $this->assertSame(3723, $s->keDetik('62:03'));
        $this->assertSame('01:30:00', $s->keWaktu($s->keDetik('90:00')));
    }

    public function test_durasi_yang_mustahil_tetap_ditolak(): void
    {
        $s = app(ResultService::class);

        // Membuka batas menit tidak boleh berarti menerima angka apa pun —
        // salah ketik seperti "9000:00" harus tetap tertangkap.
        $this->assertNull($s->keDetik('9000:00'));
        $this->assertNull($s->keDetik('13:00:00'));
    }

    public function test_peserta_finis_di_atas_satu_jam_bisa_disimpan_panitia(): void
    {
        $registrasi = $this->buatPeserta();

        $this->actingAs($this->panitia)
            ->post("/panitia/hasil/{$registrasi->id}", ['waktu' => '90:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame(5400, $registrasi->fresh()->finish_seconds);
    }

    public function test_detik_dikembalikan_ke_format_jam(): void
    {
        $s = app(ResultService::class);

        $this->assertSame('01:02:03', $s->keWaktu(3723));
        $this->assertSame('00:30:23', $s->keWaktu(1823));
    }

    /* ------------------------------------------------- Input panitia */

    public function test_panitia_bisa_mencatat_waktu_finis(): void
    {
        $r = $this->buatPeserta();

        $this->actingAs($this->panitia)
            ->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10'])
            ->assertRedirect();

        $this->assertSame(1930, $r->fresh()->finish_seconds);
        $this->assertNotNull($r->fresh()->finished_at);
    }

    public function test_format_waktu_ngawur_ditolak(): void
    {
        $r = $this->buatPeserta();

        $this->actingAs($this->panitia)
            ->post("/panitia/hasil/{$r->id}", ['waktu' => 'sepuluh menit'])
            ->assertSessionHasErrors('waktu');

        $this->assertNull($r->fresh()->finish_seconds);
    }

    public function test_waktu_yang_mustahil_ditolak(): void
    {
        $r = $this->buatPeserta();

        // 3 menit untuk 5K bukan rekor dunia — hampir pasti salah ketik.
        $this->actingAs($this->panitia)
            ->post("/panitia/hasil/{$r->id}", ['waktu' => '00:03:00'])
            ->assertSessionHasErrors('waktu');

        $this->assertNull($r->fresh()->finish_seconds);
    }

    public function test_waktu_bisa_dihapus_kalau_salah(): void
    {
        $r = $this->buatPeserta();
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '']);

        $this->assertNull($r->fresh()->finish_seconds);
        $this->assertNull($r->fresh()->rank_overall);
    }

    public function test_peserta_belum_lunas_tidak_bisa_dicatat(): void
    {
        $r = $this->buatPeserta(['status' => Registration::STATUS_PENDING_PAYMENT]);

        $this->actingAs($this->panitia)
            ->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10'])
            ->assertStatus(422);
    }

    public function test_peserta_tidak_bisa_mencatat_hasil(): void
    {
        $r = $this->buatPeserta();

        $this->actingAs($this->peserta)
            ->post("/panitia/hasil/{$r->id}", ['waktu' => '00:20:00'])
            ->assertForbidden();
    }

    /* ------------------------------------------------------ Peringkat */

    public function test_peringkat_dihitung_urut_waktu(): void
    {
        $lambat = $this->buatPeserta(['nama' => 'Lambat']);
        $cepat = $this->buatPeserta(['nama' => 'Cepat']);
        $sedang = $this->buatPeserta(['nama' => 'Sedang']);

        $this->actingAs($this->panitia)->post("/panitia/hasil/{$lambat->id}", ['waktu' => '00:45:00']);
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$cepat->id}", ['waktu' => '00:22:00']);
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$sedang->id}", ['waktu' => '00:33:00']);

        $this->assertSame(1, $cepat->fresh()->rank_overall);
        $this->assertSame(2, $sedang->fresh()->rank_overall);
        $this->assertSame(3, $lambat->fresh()->rank_overall);
    }

    public function test_peringkat_putra_dan_putri_dihitung_terpisah(): void
    {
        $putra1 = $this->buatPeserta(['gender' => 'L']);
        $putri1 = $this->buatPeserta(['gender' => 'P']);
        $putra2 = $this->buatPeserta(['gender' => 'L']);

        $this->actingAs($this->panitia)->post("/panitia/hasil/{$putra1->id}", ['waktu' => '00:25:00']);
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$putri1->id}", ['waktu' => '00:28:00']);
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$putra2->id}", ['waktu' => '00:30:00']);

        // Umum: 1, 2, 3. Per gender: putra 1 & 2, putri 1.
        $this->assertSame(2, $putri1->fresh()->rank_overall);
        $this->assertSame(1, $putri1->fresh()->rank_gender);
        $this->assertSame(2, $putra2->fresh()->rank_gender);
    }

    public function test_peringkat_dihitung_ulang_saat_waktu_diperbaiki(): void
    {
        $a = $this->buatPeserta(['nama' => 'A']);
        $b = $this->buatPeserta(['nama' => 'B']);

        $this->actingAs($this->panitia)->post("/panitia/hasil/{$a->id}", ['waktu' => '00:25:00']);
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$b->id}", ['waktu' => '00:30:00']);
        $this->assertSame(1, $a->fresh()->rank_overall);

        // Ternyata waktu A salah ketik dan sebenarnya lebih lambat.
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$a->id}", ['waktu' => '00:40:00']);

        $this->assertSame(2, $a->fresh()->rank_overall);
        $this->assertSame(1, $b->fresh()->rank_overall);
    }

    public function test_peringkat_dipisah_per_kategori(): void
    {
        $lima = $this->buatPeserta(['slug' => '5k']);
        $sepuluh = $this->buatPeserta(['slug' => '10k']);

        $this->actingAs($this->panitia)->post("/panitia/hasil/{$lima->id}", ['waktu' => '00:40:00']);
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$sepuluh->id}", ['waktu' => '00:55:00']);

        // Keduanya juara di kategorinya masing-masing.
        $this->assertSame(1, $lima->fresh()->rank_overall);
        $this->assertSame(1, $sepuluh->fresh()->rank_overall);
    }

    /* --------------------------------------------------- Papan publik */

    public function test_papan_hasil_terbuka_untuk_umum(): void
    {
        $r = $this->buatPeserta();
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        $this->get('/hasil')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Results/Index')
                ->where('sudahAda', true)
                ->has('hasil.data', 1)
                ->where('hasil.data.0.waktu', '00:32:10'));
    }

    public function test_papan_hasil_tidak_membocorkan_data_pribadi(): void
    {
        $r = $this->buatPeserta();
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        $this->get('/hasil')->assertInertia(fn ($p) => $p
            ->missing('hasil.data.0.participant_email')
            ->missing('hasil.data.0.participant_phone')
            ->missing('hasil.data.0.emergency_phone'));
    }

    public function test_peserta_tanpa_waktu_tidak_muncul_di_papan(): void
    {
        $this->buatPeserta();   // tanpa waktu

        $this->get('/hasil')->assertInertia(fn ($p) => $p
            ->where('sudahAda', false)
            ->has('hasil.data', 0));
    }

    /* ------------------------------------------------------ Sertifikat */

    public function test_peserta_bisa_membuka_sertifikatnya_sendiri(): void
    {
        $r = $this->buatPeserta();
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        $this->actingAs($this->peserta)
            ->get("/sertifikat/{$r->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Certificate/Show')
                ->where('sertifikat.waktu', '00:32:10')
                ->where('sertifikat.peringkat', 1));
    }

    public function test_sertifikat_belum_ada_sebelum_waktunya_dicatat(): void
    {
        $r = $this->buatPeserta();

        $this->actingAs($this->peserta)->get("/sertifikat/{$r->id}")->assertNotFound();
    }

    public function test_sertifikat_orang_lain_tidak_bisa_dibuka(): void
    {
        $lain = User::create([
            'name' => 'Lain', 'email' => 'lain@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);

        $r = $this->buatPeserta();
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        $this->actingAs($lain)->get("/sertifikat/{$r->id}")->assertForbidden();

        // Panitia tetap boleh, untuk membantu peserta yang kesulitan.
        $this->actingAs($this->panitia)->get("/sertifikat/{$r->id}")->assertOk();
    }

    public function test_tamu_tidak_bisa_membuka_sertifikat(): void
    {
        $r = $this->buatPeserta();
        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        auth()->logout();

        $this->get("/sertifikat/{$r->id}")->assertRedirect('/masuk');
    }

    public function test_tombol_sertifikat_muncul_di_detail_setelah_ada_waktu(): void
    {
        $r = $this->buatPeserta();

        $this->actingAs($this->peserta)
            ->get("/pendaftaran/{$r->id}")
            ->assertInertia(fn ($p) => $p->where('registration.has_certificate', false));

        $this->actingAs($this->panitia)->post("/panitia/hasil/{$r->id}", ['waktu' => '00:32:10']);

        $this->actingAs($this->peserta)
            ->get("/pendaftaran/{$r->id}")
            ->assertInertia(fn ($p) => $p
                ->where('registration.has_certificate', true)
                ->where('registration.finish_time', '00:32:10'));
    }
}
