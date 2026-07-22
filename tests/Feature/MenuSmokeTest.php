<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Memastikan setiap halaman & aksi yang bisa diklik dari menu benar-benar hidup:
 * tidak 404, tidak 500, dan merender komponen yang benar.
 */
class MenuSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $peserta;

    private User $panitia;

    private Registration $registration;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RaceCategorySeeder::class);

        $this->peserta = User::create([
            'name' => 'Peserta Smoke', 'email' => 'smoke.peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(), 'phone' => '0812',
        ]);
        $this->panitia = User::create([
            'name' => 'Panitia Smoke', 'email' => 'smoke.panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);

        $category = RaceCategory::where('slug', '10k')->first();

        $this->actingAs($this->peserta)->post('/pendaftaran', [
            'race_category_id' => $category->id,
            'participant_name' => 'Peserta Smoke',
            'participant_email' => 'smoke@example.com',
            'participant_phone' => '081355667788',
            'gender' => 'P', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Y', 'emergency_phone' => '0812',
            'agreement' => true,
        ]);
        $this->registration = Registration::first();

        $this->actingAs($this->peserta)->post("/pendaftaran/{$this->registration->id}/pembayaran", [
            'method' => 'transfer', 'sender_name' => 'Pengirim',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);
        $this->payment = Payment::first();
    }

    public static function halamanPublik(): array
    {
        return [
            'landing' => ['/', 'Landing'],
            'masuk' => ['/masuk', 'Auth/Login'],
            'daftar akun' => ['/daftar-akun', 'Auth/Register'],
        ];
    }

    /** @dataProvider halamanPublik */
    public function test_halaman_publik_terbuka(string $url, string $component): void
    {
        auth()->logout();

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }

    public function test_halaman_tamu_dialihkan_sesuai_peran(): void
    {
        $this->actingAs($this->peserta)->get('/masuk')->assertRedirect('/dashboard');
        $this->actingAs($this->peserta)->get('/daftar-akun')->assertRedirect('/dashboard');

        // Panitia tidak boleh mendarat di dashboard peserta.
        $this->actingAs($this->panitia)->get('/masuk')->assertRedirect('/panitia');
        $this->actingAs($this->panitia)->get('/lupa-kata-sandi')->assertRedirect('/panitia');
    }

    public function test_semua_halaman_peserta_terbuka(): void
    {
        $halaman = [
            '/dashboard' => 'Peserta/Dashboard',
            '/pendaftaran/baru' => 'Peserta/RegistrationForm',
            "/pendaftaran/{$this->registration->id}" => 'Peserta/RegistrationDetail',
            '/profil' => 'Profile',
        ];

        foreach ($halaman as $url => $component) {
            $this->actingAs($this->peserta)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_semua_halaman_panitia_terbuka(): void
    {
        $halaman = [
            '/panitia' => 'Panitia/Dashboard',
            '/panitia/pendaftaran' => 'Panitia/Registrations',
            "/panitia/pendaftaran/{$this->registration->id}" => 'Panitia/RegistrationDetail',
            '/panitia/kategori' => 'Panitia/Categories',
        ];

        foreach ($halaman as $url => $component) {
            $this->actingAs($this->panitia)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_filter_dan_pencarian_data_peserta_bekerja(): void
    {
        $this->actingAs($this->panitia)
            ->get('/panitia/pendaftaran?search=Smoke&status=waiting_verification&category=10k')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/Registrations')
                ->has('registrations.data', 1));

        // Pencarian yang tidak cocok harus mengembalikan tabel kosong, bukan error.
        $this->actingAs($this->panitia)
            ->get('/panitia/pendaftaran?search=tidak-ada-orang-ini')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('registrations.data', 0));
    }

    public function test_ekspor_csv_terunduh(): void
    {
        $response = $this->actingAs($this->panitia)->get('/panitia/pendaftaran/ekspor');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Peserta Smoke', $response->streamedContent());
    }

    public function test_tombol_simpan_catatan_panitia_bekerja(): void
    {
        $this->actingAs($this->panitia)
            ->patch("/panitia/pendaftaran/{$this->registration->id}/catatan", [
                'panitia_note' => 'Race pack sudah diambil.',
            ])
            ->assertRedirect();

        $this->assertSame('Race pack sudah diambil.', $this->registration->fresh()->panitia_note);
    }

    public function test_tombol_simpan_kategori_bekerja(): void
    {
        $this->actingAs($this->panitia)
            ->patch('/panitia/kategori/5k', [
                'name' => 'Fun Run 5K',
                'tagline' => 'Tagline baru',
                'price' => 120000,
                'quota' => 350,
                'is_active' => true,
            ])
            ->assertRedirect();

        $category = RaceCategory::where('slug', '5k')->first();

        $this->assertSame(120000, $category->price);
        $this->assertSame(350, $category->quota);
        $this->assertSame('Tagline baru', $category->tagline);
    }

    public function test_perubahan_kategori_langsung_terlihat_di_landing_page(): void
    {
        $this->actingAs($this->panitia)->patch('/panitia/kategori/5k', [
            'name' => 'Fun Run 5K',
            'price' => 125000,
            'quota' => 400,
            'is_active' => true,
        ]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('categories.0.price', 125000)
            ->where('categories.0.quota', 400));
    }

    public function test_kategori_yang_ditutup_hilang_dari_landing_page(): void
    {
        $this->actingAs($this->panitia)->patch('/panitia/kategori/5k', [
            'name' => 'Fun Run 5K',
            'price' => 100000,
            'quota' => 300,
            'is_active' => false,
        ]);

        $this->get('/')->assertInertia(fn ($page) => $page->has('categories', 1));
    }

    public function test_tombol_ubah_profil_dan_kata_sandi_bekerja(): void
    {
        $this->actingAs($this->peserta)
            ->patch('/profil', [
                'name' => 'Nama Baru',
                'email' => 'smoke.peserta@example.com',
                'phone' => '081999888777',
                'city' => 'Limboto',
            ])
            ->assertRedirect();

        $this->assertSame('Nama Baru', $this->peserta->fresh()->name);

        $this->actingAs($this->peserta)
            ->put('/profil/kata-sandi', [
                'current_password' => 'rahasia12345',
                'password' => 'sandibaru12345',
                'password_confirmation' => 'sandibaru12345',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            auth()->validate(['email' => 'smoke.peserta@example.com', 'password' => 'sandibaru12345'])
        );
    }

    public function test_kata_sandi_salah_ditolak_saat_ganti_sandi(): void
    {
        $this->actingAs($this->peserta)
            ->put('/profil/kata-sandi', [
                'current_password' => 'sandi-yang-salah',
                'password' => 'sandibaru12345',
                'password_confirmation' => 'sandibaru12345',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_tombol_batalkan_pendaftaran_bekerja(): void
    {
        $this->actingAs($this->peserta)
            ->post("/pendaftaran/{$this->registration->id}/batal")
            ->assertRedirect('/dashboard');

        $this->assertSame(Registration::STATUS_CANCELLED, $this->registration->fresh()->status);
    }

    public function test_pendaftaran_lunas_tidak_bisa_dibatalkan_peserta(): void
    {
        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$this->payment->id}/setujui");

        $this->actingAs($this->peserta)->post("/pendaftaran/{$this->registration->id}/batal");

        $this->assertSame(Registration::STATUS_CONFIRMED, $this->registration->fresh()->status);
    }

    public function test_alur_verifikasi_panitia_lengkap_dari_antrean_sampai_bib(): void
    {
        // Antrean menampilkan pendaftaran yang menunggu.
        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page
                ->has('pendingQueue', 1)
                ->where('stats.waiting', 1));

        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$this->payment->id}/setujui");

        // Setelah disetujui antrean kosong dan statistik berpindah ke "lunas".
        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page
                ->has('pendingQueue', 0)
                ->where('stats.confirmed', 1)
                ->where('stats.waiting', 0)
                ->where('stats.revenue', 150000));

        // Peserta melihat e-tiket dengan nomor BIB.
        $this->actingAs($this->peserta)
            ->get("/pendaftaran/{$this->registration->id}")
            ->assertInertia(fn ($page) => $page
                ->where('registration.status', 'confirmed')
                ->where('registration.bib_number', '10001'));
    }

    public function test_lencana_antrean_hanya_dibagikan_ke_panitia(): void
    {
        // Panitia melihat jumlah bukti yang menunggu sebagai lencana di sidebar.
        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page->where('panitia.waiting', 1));

        // Peserta tidak boleh menerima data operasional ini sama sekali.
        $this->actingAs($this->peserta)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('panitia', null));
    }

    public function test_lencana_antrean_berkurang_setelah_diverifikasi(): void
    {
        $this->actingAs($this->panitia)->post("/panitia/pembayaran/{$this->payment->id}/setujui");

        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertInertia(fn ($page) => $page->where('panitia.waiting', 0));
    }

    public function test_peserta_yang_sempat_membuka_url_panitia_tidak_mendarat_di_403(): void
    {
        auth()->logout();

        // Tamu mencoba membuka panel panitia -> tujuan itu tersimpan di sesi.
        $this->get('/panitia/pendaftaran')->assertRedirect('/masuk');

        $this->post('/masuk', [
            'email' => $this->peserta->email,
            'password' => 'rahasia12345',
        ])->assertRedirect('/dashboard');
    }

    public function test_panitia_tetap_diantar_ke_tujuan_semula_setelah_masuk(): void
    {
        auth()->logout();

        $this->get('/panitia/kategori')->assertRedirect('/masuk');

        $this->post('/masuk', [
            'email' => $this->panitia->email,
            'password' => 'rahasia12345',
        ])->assertRedirect('/panitia/kategori');
    }

    public function test_biaya_pendaftaran_tidak_dikirim_ke_pengunjung_yang_belum_masuk(): void
    {
        auth()->logout();

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('categories.0.price', null)
            ->where('categories.1.price', null));

        // Harganya juga tidak boleh terselip di sumber halaman.
        $this->get('/')
            ->assertDontSee('100000')
            ->assertDontSee('150000');
    }

    public function test_biaya_muncul_setelah_peserta_masuk(): void
    {
        $this->actingAs($this->peserta)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.price', 100000)
                ->where('categories.1.price', 150000));
    }

    public function test_jumlah_pendaftar_tidak_dibagikan_ke_publik(): void
    {
        auth()->logout();

        $this->get('/')->assertInertia(fn ($page) => $page
            ->missing('stats.confirmed')
            ->has('stats.total_quota'));
    }

    public function test_dashboard_panitia_memuat_tren_dan_kemajuan_kuota(): void
    {
        $this->actingAs($this->panitia)
            ->get('/panitia')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/Dashboard')
                // Grafik selalu 14 hari, termasuk hari yang kosong.
                ->has('trend', 14)
                ->where('stats.quota_total', 3000)
                ->has('stats.quota_taken')
                ->has('stats.today')
                ->has('event.days_left'));
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $this->actingAs($this->peserta)
            ->post('/keluar')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
