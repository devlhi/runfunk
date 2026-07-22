<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sponsor, lonceng notifikasi, dan cetak BIB massal.
 */
class PanitiaToolsTest extends TestCase
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

    private function daftar(string $slug = '5k', string $nama = 'Pelari Uji'): Registration
    {
        $category = RaceCategory::where('slug', $slug)->first();

        $this->actingAs($this->peserta)->post('/pendaftaran', [
            'race_category_id' => $category->id,
            'participant_name' => $nama,
            'participant_email' => 'a@b.com',
            'participant_phone' => '0812',
            'gender' => 'L', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'agreement' => true,
        ]);

        return Registration::latest('id')->first();
    }

    private function lunasi(Registration $registration): Registration
    {
        $this->actingAs($this->peserta)->post("/pendaftaran/{$registration->id}/pembayaran", [
            'method' => 'transfer', 'sender_name' => 'Pengirim',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $payment = Payment::where('registration_id', $registration->id)->latest('id')->first();
        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$payment->id}/setujui");

        return $registration->fresh();
    }

    /* --------------------------------------------------------- Sponsor */

    public function test_panitia_bisa_menambah_mengubah_dan_menghapus_sponsor(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/sponsor', [
            'name' => 'Toko Sepatu Limboto',
            'tier' => Sponsor::TIER_UTAMA,
            'website_url' => 'https://contoh.id',
            'note' => 'Menyumbang 20 kaos',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertRedirect();

        $sponsor = Sponsor::first();
        $this->assertSame('Toko Sepatu Limboto', $sponsor->name);

        $this->actingAs($this->panitia)->patch("/panitia/sponsor/{$sponsor->id}", [
            'name' => 'Toko Sepatu Limboto',
            'tier' => Sponsor::TIER_MEDIA,
            'website_url' => null,
            'note' => null,
            'is_active' => false,
            'sort_order' => 5,
        ])->assertRedirect();

        $this->assertSame(Sponsor::TIER_MEDIA, $sponsor->fresh()->tier);
        $this->assertFalse($sponsor->fresh()->is_active);

        $this->actingAs($this->panitia)->delete("/panitia/sponsor/{$sponsor->id}")->assertRedirect();
        $this->assertSame(0, Sponsor::count());
    }

    public function test_sponsor_aktif_tampil_di_landing_page_sesuai_urutan(): void
    {
        Sponsor::create(['name' => 'Media Lokal', 'tier' => Sponsor::TIER_MEDIA, 'sort_order' => 1, 'is_active' => true]);
        Sponsor::create(['name' => 'Pemdes Tuladenggi', 'tier' => Sponsor::TIER_UTAMA, 'sort_order' => 1, 'is_active' => true]);
        Sponsor::create(['name' => 'Disembunyikan', 'tier' => Sponsor::TIER_UTAMA, 'sort_order' => 0, 'is_active' => false]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('sponsors', 2)
            // Sponsor utama harus tampil lebih dulu daripada media partner.
            ->where('sponsors.0.name', 'Pemdes Tuladenggi')
            ->where('sponsors.1.name', 'Media Lokal'));
    }

    public function test_peserta_tidak_bisa_menyentuh_data_sponsor(): void
    {
        $sponsor = Sponsor::create(['name' => 'Sponsor', 'tier' => Sponsor::TIER_UTAMA, 'is_active' => true]);

        $this->actingAs($this->peserta)->get('/panitia/sponsor')->assertForbidden();
        $this->actingAs($this->peserta)->post('/panitia/sponsor', ['name' => 'Nakal'])->assertForbidden();
        $this->actingAs($this->peserta)->delete("/panitia/sponsor/{$sponsor->id}")->assertForbidden();

        $this->assertSame(1, Sponsor::count());
    }

    public function test_alamat_situs_sponsor_harus_url_yang_sah(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/sponsor', [
            'name' => 'Sponsor', 'tier' => Sponsor::TIER_UTAMA,
            'website_url' => 'bukan-url', 'is_active' => true, 'sort_order' => 0,
        ])->assertSessionHasErrors('website_url');
    }

    /* ---------------------------------------------------- Lonceng notif */

    public function test_lonceng_menampilkan_pendaftar_terbaru(): void
    {
        $this->daftar('5k', 'Rian Pelari');
        $this->daftar('10k', 'Sitti Rahma');

        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page
                ->has('panitia.feed', 2)
                // Yang terbaru muncul paling atas.
                ->where('panitia.feed.0.name', 'Sitti Rahma')
                ->where('panitia.feed.0.category', '10K')
                ->where('panitia.unread', 2));
    }

    public function test_membaca_notifikasi_menghapus_angka_merahnya(): void
    {
        $this->daftar();

        $this->actingAs($this->panitia)->post('/panitia/notifikasi/tandai-dibaca')->assertRedirect();

        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page->where('panitia.unread', 0)->has('panitia.feed', 1));
    }

    public function test_pendaftar_baru_setelah_dibaca_kembali_terhitung(): void
    {
        $this->daftar('5k', 'Yang Lama');
        $this->actingAs($this->panitia)->post('/panitia/notifikasi/tandai-dibaca');

        $this->travel(2)->minutes();
        $this->daftar('10k', 'Yang Baru');

        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page->where('panitia.unread', 1));
    }

    public function test_notifikasi_terbaca_terpisah_antar_panitia(): void
    {
        $panitiaLain = User::create([
            'name' => 'Panitia Dua', 'email' => 'dua@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);

        $this->daftar();
        $this->actingAs($this->panitia)->post('/panitia/notifikasi/tandai-dibaca');

        // Panitia lain belum membacanya, jadi angkanya harus tetap ada.
        $this->actingAs($panitiaLain)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page->where('panitia.unread', 1));
    }

    public function test_peserta_tidak_menerima_data_notifikasi(): void
    {
        $this->daftar();

        $this->actingAs($this->peserta)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('panitia', null));

        $this->actingAs($this->peserta)
            ->post('/panitia/notifikasi/tandai-dibaca')
            ->assertForbidden();
    }

    /* ---------------------------------------------------- Pratinjau BIB */

    public function test_daftar_peserta_membawa_data_untuk_pratinjau_bib(): void
    {
        // Kartu pratinjau dirender dari baris tabel, tanpa permintaan tambahan —
        // jadi semua kolom yang dicetak di kartu harus ikut di payload daftar.
        $lunas = $this->lunasi($this->daftar('5k', 'Pelari Lunas'));

        $this->actingAs($this->panitia)
            ->get('/panitia/pendaftaran')
            ->assertInertia(fn ($page) => $page
                ->where('registrations.data.0.bib_number', $lunas->bib_number)
                ->where('registrations.data.0.participant_name', 'Pelari Lunas')
                ->where('registrations.data.0.city', 'Gorontalo')
                ->where('registrations.data.0.jersey_size', 'M')
                ->where('registrations.data.0.emergency_name', 'Ibu')
                ->where('registrations.data.0.emergency_phone', '0813')
                ->has('registrations.data.0.blood_type'));
    }

    public function test_peserta_belum_lunas_tidak_punya_bib_untuk_dipratinjau(): void
    {
        $this->daftar('5k', 'Belum Bayar');

        // Tombol pratinjau di UI bergantung pada nomor BIB; selama masih null,
        // tombolnya memang tidak boleh muncul.
        $this->actingAs($this->panitia)
            ->get('/panitia/pendaftaran')
            ->assertInertia(fn ($page) => $page->where('registrations.data.0.bib_number', null));
    }

    /* -------------------------------------------------------- Cetak BIB */

    public function test_halaman_cetak_hanya_memuat_pendaftaran_lunas(): void
    {
        $lunas = $this->lunasi($this->daftar('5k', 'Sudah Lunas'));
        $this->daftar('10k', 'Belum Bayar');

        $this->actingAs($this->panitia)
            ->get('/panitia/cetak-bib')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/BibPrint')
                ->has('registrations.data', 1)
                ->where('registrations.data.0.name', 'Sudah Lunas')
                ->where('registrations.data.0.bib_number', $lunas->bib_number));
    }

    public function test_pilih_semua_menjangkau_peserta_di_luar_halaman_pertama(): void
    {
        // Barisnya dipaginasi, tapi "pilih semua" harus tetap mengenai SEMUA
        // peserta — kalau hanya halaman terbuka, panitia mengira sudah mencetak
        // semuanya padahal sebagian tertinggal.
        //
        // Satu akun hanya boleh punya satu pendaftaran aktif per kategori, jadi
        // tiap pelari di sini memakai akunnya sendiri.
        $ids = collect(range(1, 3))
            ->map(function ($i) {
                $this->peserta = User::create([
                    'name' => "Pelari {$i}", 'email' => "pelari{$i}@example.com",
                    'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
                    'email_verified_at' => now(),
                ]);

                return $this->lunasi($this->daftar('5k', "Pelari {$i}"))->id;
            })
            ->all();

        $this->actingAs($this->panitia)
            ->get('/panitia/cetak-bib')
            ->assertInertia(fn ($page) => $page
                ->has('semuaId', 3)
                ->where('semuaId', $ids));
    }

    public function test_pilih_semua_ikut_mengikuti_saringan_kategori(): void
    {
        $this->lunasi($this->daftar('5k', 'Pelari 5K'));
        $sepuluh = $this->lunasi($this->daftar('10k', 'Pelari 10K'));

        $this->actingAs($this->panitia)
            ->get('/panitia/cetak-bib?category=10k')
            ->assertInertia(fn ($page) => $page->where('semuaId', [$sepuluh->id]));
    }

    public function test_daftar_cetak_bisa_disaring_per_kategori(): void
    {
        $this->lunasi($this->daftar('5k', 'Pelari 5K'));
        $this->lunasi($this->daftar('10k', 'Pelari 10K'));

        $this->actingAs($this->panitia)
            ->get('/panitia/cetak-bib?category=10k')
            ->assertInertia(fn ($page) => $page
                ->has('registrations.data', 1)
                ->where('registrations.data.0.name', 'Pelari 10K'));
    }

    public function test_lembar_cetak_hanya_memuat_bib_yang_dipilih(): void
    {
        $satu = $this->lunasi($this->daftar('5k', 'Peserta Satu'));
        $dua = $this->lunasi($this->daftar('10k', 'Peserta Dua'));

        $this->actingAs($this->panitia)
            ->get("/panitia/cetak-bib/lembar?ids={$dua->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/BibSheet')
                ->has('registrations', 1)
                ->where('registrations.0.name', 'Peserta Dua'));
    }

    public function test_lembar_cetak_menolak_id_pendaftaran_yang_belum_lunas(): void
    {
        $belum = $this->daftar('5k', 'Belum Bayar');

        $this->actingAs($this->panitia)
            ->get("/panitia/cetak-bib/lembar?ids={$belum->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('registrations', 0));
    }

    public function test_lembar_cetak_butuh_daftar_id(): void
    {
        $this->actingAs($this->panitia)
            ->get('/panitia/cetak-bib/lembar')
            ->assertSessionHasErrors('ids');
    }

    public function test_peserta_tidak_bisa_membuka_halaman_cetak_bib(): void
    {
        $lunas = $this->lunasi($this->daftar());

        $this->actingAs($this->peserta)->get('/panitia/cetak-bib')->assertForbidden();
        $this->actingAs($this->peserta)->get("/panitia/cetak-bib/lembar?ids={$lunas->id}")->assertForbidden();
    }
}
