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
 * Uji penetrasi ringan: setiap tes di sini mencoba MENYALAHGUNAKAN aplikasi.
 * Tes yang gagal berarti ada celah yang harus ditutup.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);
    }

    private function user(string $email, string $role = User::ROLE_PESERTA): User
    {
        return User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'rahasia12345',
            'role' => $role,
            'phone' => '081200001111',
            // Memerankan akun yang sudah mapan, bukan pendaftar yang baru menekan
            // tombol daftar — slot lomba baru terbuka setelah emailnya terverifikasi.
            'email_verified_at' => now(),
        ]);
    }

    private function formData(RaceCategory $category, array $override = []): array
    {
        return [
            'race_category_id' => $category->id,
            'participant_name' => 'Peserta Uji',
            'participant_email' => 'peserta@example.com',
            'participant_phone' => '081355667788',
            'gender' => 'P',
            'birth_date' => '1996-08-17',
            'city' => 'Gorontalo',
            'jersey_size' => 'M',
            'emergency_name' => 'Yusuf',
            'emergency_phone' => '081244556677',
            'agreement' => true,
            ...$override,
        ];
    }

    private function makeRegistration(User $user, string $slug = '10k'): Registration
    {
        $category = RaceCategory::where('slug', $slug)->first();
        $this->actingAs($user)->post('/pendaftaran', $this->formData($category));

        return Registration::where('user_id', $user->id)->latest('id')->first();
    }

    private function uploadProof(User $user, Registration $registration): Payment
    {
        $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
            'method' => 'transfer',
            'sender_name' => 'Pengirim',
            'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        return Payment::where('registration_id', $registration->id)->latest('id')->first();
    }

    /* ------------------------------------------------ Eskalasi hak akses */

    public function test_peserta_tidak_bisa_menaikkan_dirinya_jadi_panitia_lewat_profil(): void
    {
        $user = $this->user('peserta@example.com');

        $this->actingAs($user)->patch('/profil', [
            'name' => 'Peserta Nakal',
            'email' => 'peserta@example.com',
            'phone' => '081200001111',
            'role' => User::ROLE_PANITIA,
        ]);

        $this->assertSame(User::ROLE_PESERTA, $user->fresh()->role, 'Role tidak boleh bisa diubah lewat form profil');
    }

    public function test_peserta_tidak_bisa_menyetujui_pembayarannya_sendiri(): void
    {
        Storage::fake('public');
        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        $this->actingAs($user)
            ->post("/panitia/pembayaran/{$payment->id}/setujui")
            ->assertForbidden();

        $this->assertSame(Registration::STATUS_WAITING_VERIFICATION, $registration->fresh()->status);
        $this->assertNull($registration->fresh()->bib_number);
    }

    public function test_peserta_tidak_bisa_mengubah_harga_dan_kuota_kategori(): void
    {
        $user = $this->user('peserta@example.com');

        $this->actingAs($user)
            ->patch('/panitia/kategori/10k', [
                'name' => 'Gratis',
                'price' => 0,
                'quota' => 99999,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertSame(150000, RaceCategory::where('slug', '10k')->first()->price);
    }

    public function test_peserta_tidak_bisa_menulis_catatan_panitia(): void
    {
        $owner = $this->user('owner@example.com');
        $registration = $this->makeRegistration($owner);

        $this->actingAs($owner)
            ->patch("/panitia/pendaftaran/{$registration->id}/catatan", ['panitia_note' => 'Lunas kok'])
            ->assertForbidden();
    }

    /* ------------------------------------------------------------- IDOR */

    public function test_peserta_tidak_bisa_mengunggah_bukti_ke_pendaftaran_orang_lain(): void
    {
        Storage::fake('public');
        $owner = $this->user('owner@example.com');
        $penyerang = $this->user('penyerang@example.com');
        $registration = $this->makeRegistration($owner);

        $this->actingAs($penyerang)
            ->post("/pendaftaran/{$registration->id}/pembayaran", [
                'method' => 'transfer',
                'sender_name' => 'Penyerang',
                'paid_at' => now()->toDateString(),
                'proof' => UploadedFile::fake()->image('palsu.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame(0, Payment::count());
    }

    public function test_peserta_tidak_bisa_membatalkan_pendaftaran_orang_lain(): void
    {
        $owner = $this->user('owner@example.com');
        $penyerang = $this->user('penyerang@example.com');
        $registration = $this->makeRegistration($owner);

        $this->actingAs($penyerang)
            ->post("/pendaftaran/{$registration->id}/batal")
            ->assertForbidden();

        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registration->fresh()->status);
    }

    public function test_tamu_tidak_bisa_mengekspor_data_peserta(): void
    {
        $this->get('/panitia/pendaftaran/ekspor')->assertRedirect('/masuk');
    }

    /* --------------------------------------------- Manipulasi nilai uang */

    public function test_peserta_tidak_bisa_menentukan_harga_sendiri(): void
    {
        $user = $this->user('peserta@example.com');
        $category = RaceCategory::where('slug', '10k')->first();

        $this->actingAs($user)->post('/pendaftaran', $this->formData($category, [
            'amount' => 1000,
            'status' => Registration::STATUS_CONFIRMED,
            'bib_number' => '9999',
        ]));

        $registration = Registration::first();

        $this->assertSame(150000, $registration->amount, 'Harga wajib diambil dari kategori, bukan dari input');
        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registration->status);
        $this->assertNull($registration->bib_number);
    }

    public function test_nominal_pembayaran_mengikuti_tagihan_bukan_input_peserta(): void
    {
        Storage::fake('public');
        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);

        $this->actingAs($user)->post("/pendaftaran/{$registration->id}/pembayaran", [
            'method' => 'transfer',
            'sender_name' => 'Pengirim',
            'paid_at' => now()->toDateString(),
            'amount' => 1,
            'status' => Payment::STATUS_APPROVED,
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $payment = Payment::first();

        $this->assertSame(150000, $payment->amount);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    /* ------------------------------------------------ Unggahan berbahaya */

    public function test_file_php_tidak_bisa_diunggah_sebagai_bukti(): void
    {
        Storage::fake('public');
        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);

        $this->actingAs($user)
            ->post("/pendaftaran/{$registration->id}/pembayaran", [
                'method' => 'transfer',
                'sender_name' => 'Pengirim',
                'paid_at' => now()->toDateString(),
                'proof' => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>'),
            ])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, Payment::count());
    }

    public function test_file_php_bersamaran_jpg_tetap_ditolak(): void
    {
        Storage::fake('local');
        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);

        // UploadedFile::fake() menebak MIME dari ekstensi, jadi tidak cukup untuk
        // menguji ini. Kita pakai berkas asli supaya MIME-nya benar-benar dibaca
        // dari isinya, persis seperti unggahan sungguhan.
        $path = tempnam(sys_get_temp_dir(), 'shell').'.jpg';
        file_put_contents($path, '<?php system($_GET["c"]); ?>');
        $file = new UploadedFile($path, 'shell.jpg', null, null, true);

        $this->actingAs($user)
            ->post("/pendaftaran/{$registration->id}/pembayaran", [
                'method' => 'transfer',
                'sender_name' => 'Pengirim',
                'paid_at' => now()->toDateString(),
                'proof' => $file,
            ])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, Payment::count());
        @unlink($path);
    }

    /* ------------------------------------------------------ Logika bisnis */

    public function test_pendaftaran_yang_sudah_dibatalkan_tidak_bisa_diloloskan_panitia(): void
    {
        Storage::fake('public');
        $user = $this->user('peserta@example.com');
        $panitia = $this->user('panitia@example.com', User::ROLE_PANITIA);

        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        // Peserta membatalkan setelah mengirim bukti.
        $registration->update(['status' => Registration::STATUS_CANCELLED]);

        $this->actingAs($panitia)->post("/panitia/pembayaran/{$payment->id}/setujui");

        $this->assertSame(
            Registration::STATUS_CANCELLED,
            $registration->fresh()->status,
            'Pendaftaran batal tidak boleh berubah jadi lunas'
        );
        $this->assertNull($registration->fresh()->bib_number, 'BIB tidak boleh terbit untuk pendaftaran batal');
    }

    public function test_kategori_yang_ditutup_tidak_menerima_pendaftaran(): void
    {
        $user = $this->user('peserta@example.com');
        $category = RaceCategory::where('slug', '10k')->first();
        $category->update(['is_active' => false]);

        $this->actingAs($user)
            ->post('/pendaftaran', $this->formData($category))
            ->assertSessionHasErrors('race_category_id');

        $this->assertSame(0, Registration::count());
    }

    public function test_bukti_yang_sudah_disetujui_tidak_bisa_ditolak_belakangan(): void
    {
        Storage::fake('public');
        $user = $this->user('peserta@example.com');
        $panitia = $this->user('panitia@example.com', User::ROLE_PANITIA);

        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        $this->actingAs($panitia)->post("/panitia/pembayaran/{$payment->id}/setujui");
        $this->actingAs($panitia)->post("/panitia/pembayaran/{$payment->id}/tolak", ['reason' => 'Berubah pikiran']);

        $this->assertSame(Registration::STATUS_CONFIRMED, $registration->fresh()->status);
    }

    /* ---------------------------------------------------- Brute force */

    public function test_login_dibatasi_setelah_percobaan_gagal_berulang(): void
    {
        $this->user('korban@example.com');

        $lastStatus = null;

        for ($i = 0; $i < 12; $i++) {
            $response = $this->from('/masuk')->post('/masuk', [
                'email' => 'korban@example.com',
                'password' => 'tebakan-salah-'.$i,
            ]);
            $lastStatus = $response;
        }

        // Setelah sekian percobaan gagal, sistem harus menahan, bukan terus melayani.
        $lastStatus->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');

        $this->assertStringContainsString(
            'coba lagi',
            mb_strtolower(implode(' ', $errors)),
            'Login harus dibatasi (throttle) setelah percobaan gagal berulang, pesan: '.implode(' ', $errors)
        );
    }

    /* ------------------------------------------------- Injeksi ke CSV */

    public function test_ekspor_csv_tidak_bisa_disisipi_rumus_berbahaya(): void
    {
        $panitia = $this->user('panitia@example.com', User::ROLE_PANITIA);
        $user = $this->user('peserta@example.com');
        $category = RaceCategory::where('slug', '5k')->first();

        $this->actingAs($user)->post('/pendaftaran', $this->formData($category, [
            'participant_name' => '=cmd|\' /C calc\'!A0',
            'community' => '@SUM(1+1)*cmd|\' /C calc\'!A0',
        ]));

        $response = $this->actingAs($panitia)->get('/panitia/pendaftaran/ekspor');
        $csv = $response->streamedContent();

        // Tidak boleh ada sel yang dimulai dengan karakter rumus, baik yang dibungkus
        // tanda kutip ("=cmd) maupun yang polos (,=cmd).
        foreach (['"=cmd', ',=cmd', '"@SUM', ',@SUM'] as $berbahaya) {
            $this->assertStringNotContainsString(
                $berbahaya,
                $csv,
                "Sel CSV tidak boleh diawali = + - @ (CSV formula injection): {$berbahaya}"
            );
        }

        // Nilainya tetap tersimpan, hanya dinetralkan dengan kutip satu di depan.
        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
    }

    public function test_bukti_bayar_tidak_bisa_dilihat_tanpa_login(): void
    {
        Storage::fake('local');
        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        auth()->logout();

        $this->get("/bukti-bayar/{$payment->id}")->assertRedirect('/masuk');
    }

    public function test_bukti_bayar_tidak_bisa_dilihat_peserta_lain(): void
    {
        Storage::fake('local');
        $user = $this->user('peserta@example.com');
        $penyerang = $this->user('penyerang@example.com');
        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        $this->actingAs($penyerang)
            ->get("/bukti-bayar/{$payment->id}")
            ->assertForbidden();
    }

    public function test_bukti_bayar_bisa_dilihat_pemilik_dan_panitia(): void
    {
        Storage::fake('local');
        $user = $this->user('peserta@example.com');
        $panitia = $this->user('panitia@example.com', User::ROLE_PANITIA);
        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        $this->actingAs($user)->get("/bukti-bayar/{$payment->id}")->assertOk();
        $this->actingAs($panitia)->get("/bukti-bayar/{$payment->id}")->assertOk();
    }

    public function test_bukti_bayar_tidak_disimpan_di_folder_publik(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);
        $payment = $this->uploadProof($user, $registration);

        Storage::disk('local')->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
    }

    public function test_slot_kembali_tersedia_saat_pendaftaran_kedaluwarsa(): void
    {
        $user = $this->user('peserta@example.com');
        $category = RaceCategory::where('slug', '10k')->first();
        $registration = $this->makeRegistration($user);

        $this->assertSame(1, $category->fresh()->takenSlots());

        // Batas waktu bayar lewat tanpa ada bukti yang masuk.
        $registration->update(['expires_at' => now()->subHour()]);

        $this->assertSame(
            0,
            $category->fresh()->takenSlots(),
            'Pendaftaran kedaluwarsa tidak boleh terus mengunci kuota'
        );
    }

    public function test_perintah_pelepasan_membatalkan_pendaftaran_kedaluwarsa(): void
    {
        $user = $this->user('peserta@example.com');
        $registration = $this->makeRegistration($user);
        $registration->update(['expires_at' => now()->subHour()]);

        $this->artisan('funrun:release-expired')->assertSuccessful();

        $this->assertSame(Registration::STATUS_CANCELLED, $registration->fresh()->status);
    }
}
