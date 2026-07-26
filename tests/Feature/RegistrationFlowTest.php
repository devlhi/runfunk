<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);
    }

    private function peserta(array $attributes = []): User
    {
        return User::create([
            'name' => 'Peserta Uji',
            'email' => 'peserta.uji@example.com',
            'password' => 'rahasia12345',
            'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
            'phone' => '081200001111',
            ...$attributes,
        ]);
    }

    private function panitia(): User
    {
        return User::create([
            'name' => 'Panitia Uji',
            'email' => 'panitia.uji@example.com',
            'password' => 'rahasia12345',
            'role' => User::ROLE_PANITIA,
        ]);
    }

    private function formData(RaceCategory $category): array
    {
        return [
            'race_category_id' => $category->id,
            'participant_name' => 'Sitti Rahma',
            'participant_email' => 'sitti@example.com',
            'participant_phone' => '081355667788',
            'gender' => 'P',
            'birth_date' => '1996-08-17',
            'city' => 'Kabupaten Gorontalo',
            'jersey_size' => 'M',
            'emergency_name' => 'Yusuf',
            'emergency_phone' => '081244556677',
            'agreement' => true,
        ];
    }

    public function test_landing_page_menampilkan_kedua_kategori(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Landing')
                ->has('categories', 2)
                ->where('categories.0.distance_label', '5K')
                ->where('categories.1.distance_label', '10K'));
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/dashboard')->assertRedirect('/masuk');
        $this->get('/pendaftaran/baru')->assertRedirect('/masuk');
    }

    /* ------------------------------------------- Sambutan ketua IKA */

    public function test_sambutan_ketua_tampil_tanpa_nama_karangan(): void
    {
        // Nama sengaja kosong sampai panitia mengisinya sendiri: lebih baik hanya
        // jabatan yang tampil daripada memasang nama orang yang salah di halaman
        // depan. Yang harus selalu ada: jabatan dan isi sambutannya.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('ketua.nama', '')
            ->where('ketua.jabatan', 'Ketua IKA SMK Gotong Royong Telaga')
            ->has('ketua.pesan', 3));
    }

    public function test_nama_dan_sambutan_ketua_bisa_diganti_panitia(): void
    {
        Setting::simpan([
            'chairman_name' => 'Bapak Contoh',
            'chairman_title' => 'Ketua Umum IKA',
            'chairman_message' => "Paragraf pertama.\n\nParagraf kedua.",
        ]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('ketua.nama', 'Bapak Contoh')
            ->where('ketua.jabatan', 'Ketua Umum IKA')
            // Dipecah per paragraf, bukan dikirim sebagai satu blok teks.
            ->where('ketua.pesan', ['Paragraf pertama.', 'Paragraf kedua.']));
    }

    public function test_foto_ketua_ika_tersedia(): void
    {
        // Seksinya merujuk berkas ini secara langsung; kalau hilang, yang tampil
        // di halaman depan adalah ikon gambar rusak.
        $this->assertFileExists(public_path('images/ketua-lari.webp'));
    }

    public function test_pengunjung_bisa_membuat_akun_peserta(): void
    {
        $response = $this->post('/daftar-akun', [
            'name' => 'Rian Pelari',
            'email' => 'rian@example.com',
            'phone' => '081399998888',
            'gender' => 'L',
            'birth_date' => '1999-01-20',
            'city' => 'Gorontalo',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]);

        // Akunnya jadi dan langsung masuk, tapi diantar ke verifikasi email dulu —
        // slot lomba baru terbuka setelah alamatnya terbukti benar.
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'rian@example.com',
            'role' => User::ROLE_PESERTA,
            'email_verified_at' => null,
        ]);
    }

    public function test_peserta_bisa_mendaftar_dan_mendapat_kode_pendaftaran(): void
    {
        $user = $this->peserta();
        $category = RaceCategory::where('slug', '10k')->first();

        $this->actingAs($user)
            ->post('/pendaftaran', $this->formData($category))
            ->assertRedirect();

        $registration = Registration::first();

        $this->assertSame('GFR-10K-0001', $registration->registration_code);
        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registration->status);
        $this->assertSame(150000, $registration->amount);
        $this->assertNull($registration->bib_number);
    }

    public function test_pendaftaran_ganda_di_kategori_yang_sama_ditolak(): void
    {
        $user = $this->peserta();
        $category = RaceCategory::where('slug', '5k')->first();

        $this->actingAs($user)->post('/pendaftaran', $this->formData($category));

        $this->actingAs($user)
            ->post('/pendaftaran', $this->formData($category))
            ->assertSessionHasErrors('race_category_id');

        $this->assertSame(1, Registration::count());
    }

    public function test_pendaftaran_ditolak_saat_kuota_habis(): void
    {
        $user = $this->peserta();
        $category = RaceCategory::where('slug', '5k')->first();
        $category->update(['quota' => 0]);

        $this->actingAs($user)
            ->post('/pendaftaran', $this->formData($category))
            ->assertSessionHasErrors('race_category_id');

        $this->assertSame(0, Registration::count());
    }

    public function test_peserta_bisa_mengunggah_bukti_bayar(): void
    {
        Storage::fake('local');

        $user = $this->peserta();
        $category = RaceCategory::where('slug', '10k')->first();
        $this->actingAs($user)->post('/pendaftaran', $this->formData($category));
        $registration = Registration::first();

        $this->actingAs($user)
            ->post("/pendaftaran/{$registration->id}/pembayaran", [
                'method' => 'transfer',
                'sender_name' => 'Sitti Rahma',
                'sender_bank' => 'BRI',
                'paid_at' => now()->toDateString(),
                'proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect("/pendaftaran/{$registration->id}");

        $payment = Payment::first();

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(150000, $payment->amount);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame(
            Registration::STATUS_WAITING_VERIFICATION,
            $registration->fresh()->status
        );
    }

    public function test_panitia_menyetujui_pembayaran_dan_bib_terbit(): void
    {
        Storage::fake('local');

        $user = $this->peserta();
        $panitia = $this->panitia();
        $category = RaceCategory::where('slug', '10k')->first();

        $this->actingAs($user)->post('/pendaftaran', $this->formData($category));
        $registration = Registration::first();

        $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
            'method' => 'transfer',
            'sender_name' => 'Sitti Rahma',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $payment = Payment::first();

        $this->actingAs($panitia)
            ->post("/panitia/pembayaran/{$payment->id}/setujui")
            ->assertRedirect();

        $registration->refresh();

        $this->assertSame(Registration::STATUS_CONFIRMED, $registration->status);
        $this->assertSame('10001', $registration->bib_number, 'BIB 10K diawali angka 10');
        $this->assertSame($panitia->id, $registration->verified_by);
        $this->assertNotNull($registration->verified_at);
        $this->assertSame(Payment::STATUS_APPROVED, $payment->fresh()->status);
    }

    public function test_bib_berurutan_per_kategori(): void
    {
        Storage::fake('local');

        $panitia = $this->panitia();
        $category = RaceCategory::where('slug', '5k')->first();

        foreach (['satu@example.com', 'dua@example.com'] as $email) {
            $user = $this->peserta(['email' => $email]);

            $this->actingAs($user)->post('/pendaftaran', $this->formData($category));
            $registration = Registration::where('user_id', $user->id)->first();

            $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
                'method' => 'qris',
                'sender_name' => 'Pengirim',
                'paid_at' => now()->toDateString(),
                'proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

            $payment = Payment::where('registration_id', $registration->id)->first();
            $this->actingAs($panitia)->post("/panitia/pembayaran/{$payment->id}/setujui");
        }

        $bibs = Registration::orderBy('id')->pluck('bib_number')->all();

        $this->assertSame(['5001', '5002'], $bibs, 'BIB 5K diawali angka 5 dan berurutan');
    }

    public function test_nomor_bib_menandai_kategorinya(): void
    {
        Storage::fake('local');
        $panitia = $this->panitia();

        $bib = [];

        foreach (['5k', '10k'] as $slug) {
            $category = RaceCategory::where('slug', $slug)->first();
            $user = $this->peserta(['email' => "pelari.{$slug}@example.com"]);

            $this->actingAs($user)->post('/pendaftaran', $this->formData($category));
            $registration = Registration::where('user_id', $user->id)->first();

            $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
                'method' => 'qris',
                'sender_name' => 'Pengirim',
                'paid_at' => now()->toDateString(),
                'proof' => UploadedFile::fake()->image('bukti.jpg'),
            ]);

            $payment = Payment::where('registration_id', $registration->id)->first();
            $this->actingAs($panitia)->post("/panitia/pembayaran/{$payment->id}/setujui");

            $bib[$slug] = $registration->fresh()->bib_number;
        }

        $this->assertStringStartsWith('5', $bib['5k']);
        $this->assertStringStartsWith('10', $bib['10k']);

        // Rentangnya tidak boleh saling tabrakan sampai kuota penuh.
        $this->assertLessThan(10001, (int) $bib['5k'] + 300);
    }

    public function test_panitia_menolak_bukti_dan_peserta_bisa_unggah_ulang(): void
    {
        Storage::fake('local');

        $user = $this->peserta();
        $panitia = $this->panitia();
        $category = RaceCategory::where('slug', '5k')->first();

        $this->actingAs($user)->post('/pendaftaran', $this->formData($category));
        $registration = Registration::first();

        $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
            'method' => 'transfer',
            'sender_name' => 'Salah Orang',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $payment = Payment::first();

        $this->actingAs($panitia)
            ->post("/panitia/pembayaran/{$payment->id}/tolak", ['reason' => 'Nominal kurang Rp 50.000.'])
            ->assertRedirect();

        $registration->refresh();

        $this->assertSame(Registration::STATUS_REJECTED, $registration->status);
        $this->assertSame('Nominal kurang Rp 50.000.', $registration->panitia_note);
        $this->assertTrue($registration->canUploadProof(), 'Peserta harus bisa mengunggah ulang');

        // Unggah ulang mengembalikan status ke antrean verifikasi.
        $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
            'method' => 'transfer',
            'sender_name' => 'Sitti Rahma',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti-benar.jpg'),
        ]);

        $this->assertSame(Registration::STATUS_WAITING_VERIFICATION, $registration->fresh()->status);
        $this->assertSame(2, Payment::count());
    }

    public function test_peserta_tidak_bisa_membuka_panel_panitia(): void
    {
        $user = $this->peserta();

        $this->actingAs($user)->get('/panitia')->assertForbidden();
        $this->actingAs($user)->get('/panitia/pendaftaran')->assertForbidden();
    }

    public function test_peserta_tidak_bisa_melihat_pendaftaran_orang_lain(): void
    {
        $owner = $this->peserta();
        $lain = $this->peserta(['email' => 'lain@example.com']);
        $category = RaceCategory::where('slug', '5k')->first();

        $this->actingAs($owner)->post('/pendaftaran', $this->formData($category));
        $registration = Registration::first();

        $this->actingAs($lain)
            ->get("/pendaftaran/{$registration->id}")
            ->assertForbidden();
    }

    public function test_panitia_masuk_langsung_ke_panel_panitia(): void
    {
        $panitia = $this->panitia();

        $this->post('/masuk', [
            'email' => $panitia->email,
            'password' => 'rahasia12345',
        ])->assertRedirect('/panitia');
    }

    public function test_kuota_kategori_tidak_bisa_diturunkan_di_bawah_slot_terpakai(): void
    {
        $user = $this->peserta();
        $panitia = $this->panitia();
        $category = RaceCategory::where('slug', '5k')->first();

        $this->actingAs($user)->post('/pendaftaran', $this->formData($category));

        $this->actingAs($panitia)
            ->patch("/panitia/kategori/{$category->slug}", [
                'name' => $category->name,
                'price' => 100000,
                'quota' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('quota');
    }
}
