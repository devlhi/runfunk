<?php

namespace Tests\Feature;

use App\Mail\VerifikasiEmail;
use App\Models\EmailVerificationCode;
use App\Models\RaceCategory;
use App\Models\User;
use App\Notifications\KodeVerifikasiEmail;
use App\Services\EmailVerifier;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verifikasi email pendaftar: kode ketik 6 angka dan tombol sekali klik.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);
    }

    private function dataDaftar(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Rian Pelari',
            'email' => 'rian@example.com',
            'phone' => '081234567890',
            'gender' => 'L',
            'birth_date' => '1996-08-17',
            'city' => 'Gorontalo',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ], $ubah);
    }

    private function pesertaBelumVerifikasi(): User
    {
        return User::create([
            'name' => 'Belum Verifikasi', 'email' => 'belum@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => null,
        ]);
    }

    /* ------------------------------------------------ Saat mendaftar akun */

    public function test_akun_baru_belum_terverifikasi_dan_menerima_kode(): void
    {
        Notification::fake();

        $this->post('/daftar-akun', $this->dataDaftar())
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'rian@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);

        Notification::assertSentTo($user, KodeVerifikasiEmail::class);
    }

    public function test_kode_disimpan_terhash_bukan_apa_adanya(): void
    {
        // Kalau basis data bocor, kode yang masih berlaku tidak boleh langsung
        // bisa dipakai orang lain untuk mengaktifkan akun.
        $user = $this->pesertaBelumVerifikasi();
        $kode = app(EmailVerifier::class)->terbitkan($user)['kode'];

        $tersimpan = EmailVerificationCode::where('user_id', $user->id)->firstOrFail();

        $this->assertNotSame($kode, $tersimpan->code_hash);
        $this->assertTrue(Hash::check($kode, $tersimpan->code_hash));
    }

    public function test_kode_verifikasi_tidak_diantrekan(): void
    {
        // Berbeda dari kabar pembayaran: kode ini memblokir. Kalau diantrekan di
        // server tanpa `queue:work` — keadaan normal di pemasangan Laragon —
        // emailnya hanya menumpuk di tabel jobs dan pendaftar terjebak selamanya.
        $this->assertNotInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new KodeVerifikasiEmail('123456', 'https://contoh.test')
        );

        Notification::fake();
        $this->post('/daftar-akun', $this->dataDaftar());

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());
    }

    public function test_smtp_mati_tidak_menggagalkan_pembuatan_akun(): void
    {
        // Transport sungguhan ke host yang tidak ada. Akunnya tetap harus jadi dan
        // kodenya tetap tercatat, supaya peserta cukup menekan "Kirim ulang" nanti
        // alih-alih mendaftar ulang dari awal.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.host-yang-tidak-ada.invalid',
            'mail.mailers.smtp.port' => 587,
        ]);

        $this->post('/daftar-akun', $this->dataDaftar())
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'rian@example.com')->firstOrFail();

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_kirim_ulang_melaporkan_kegagalan_bukan_diam_diam_sukses(): void
    {
        $user = $this->pesertaBelumVerifikasi();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.host-yang-tidak-ada.invalid',
            'mail.mailers.smtp.port' => 587,
        ]);

        // Kalau ini melaporkan sukses, peserta akan menunggu email yang tidak
        // pernah datang tanpa tahu apa yang salah.
        $this->actingAs($user)
            ->post('/verifikasi-email/kirim-ulang')
            ->assertSessionHas('error');
    }

    /* ------------------------------------------------------- Kode ketikan */

    public function test_kode_benar_memverifikasi_akun(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $kode = app(EmailVerifier::class)->terbitkan($user)['kode'];

        $this->actingAs($user)
            ->post('/verifikasi-email', ['kode' => $kode])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        // Dihapus setelah dipakai supaya tidak bisa dipakai kedua kali.
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_kode_salah_ditolak_dan_menambah_hitungan_percobaan(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        app(EmailVerifier::class)->terbitkan($user);

        $this->actingAs($user)
            ->post('/verifikasi-email', ['kode' => '000000'])
            ->assertSessionHasErrors('kode');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(1, EmailVerificationCode::where('user_id', $user->id)->value('attempts'));
    }

    public function test_kode_hangus_setelah_percobaan_habis(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $kode = app(EmailVerifier::class)->terbitkan($user)['kode'];

        for ($i = 0; $i < EmailVerificationCode::MAKS_PERCOBAAN; $i++) {
            $this->actingAs($user)->post('/verifikasi-email', ['kode' => '000000']);
        }

        // Kode yang BENAR pun sudah tidak berlaku setelah ditebak berkali-kali.
        $this->actingAs($user)
            ->post('/verifikasi-email', ['kode' => $kode])
            ->assertSessionHasErrors('kode');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_kode_kedaluwarsa_ditolak(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $kode = app(EmailVerifier::class)->terbitkan($user)['kode'];

        EmailVerificationCode::where('user_id', $user->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)
            ->post('/verifikasi-email', ['kode' => $kode])
            ->assertSessionHasErrors('kode');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_minta_kode_baru_membatalkan_kode_lama(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $verifier = app(EmailVerifier::class);

        $kodeLama = $verifier->terbitkan($user)['kode'];
        $verifier->terbitkan($user);

        // Tidak boleh ada dua kode yang sama-sama sah beredar.
        $this->actingAs($user)
            ->post('/verifikasi-email', ['kode' => $kodeLama])
            ->assertSessionHasErrors('kode');

        $this->assertSame(1, EmailVerificationCode::where('user_id', $user->id)->count());
    }

    public function test_kode_milik_orang_lain_tidak_bisa_dipakai(): void
    {
        $verifier = app(EmailVerifier::class);

        $korban = $this->pesertaBelumVerifikasi();
        $kodeKorban = $verifier->terbitkan($korban)['kode'];

        $penyerang = User::create([
            'name' => 'Penyerang', 'email' => 'penyerang@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => null,
        ]);
        $verifier->terbitkan($penyerang);

        $this->actingAs($penyerang)
            ->post('/verifikasi-email', ['kode' => $kodeKorban])
            ->assertSessionHasErrors('kode');

        $this->assertNull($penyerang->fresh()->email_verified_at);
        $this->assertNull($korban->fresh()->email_verified_at);
    }

    /* ------------------------------------------------ Tombol tanda tangan */

    public function test_tautan_bertanda_tangan_memverifikasi_akun(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $tautan = app(EmailVerifier::class)->tautanBertandaTangan($user);

        $this->actingAs($user)->get($tautan)->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tautan_tanpa_tanda_tangan_ditolak(): void
    {
        $user = $this->pesertaBelumVerifikasi();

        $this->actingAs($user)
            ->get('/verifikasi-email/'.$user->id.'/'.sha1($user->email))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_tanda_tangan_kedaluwarsa_ditolak(): void
    {
        $user = $this->pesertaBelumVerifikasi();

        $tautan = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['user' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($tautan)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_tautan_orang_lain_tidak_memverifikasi_akun_sendiri(): void
    {
        $korban = $this->pesertaBelumVerifikasi();

        $penyerang = User::create([
            'name' => 'Penyerang', 'email' => 'penyerang@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => null,
        ]);

        // Tautan sah milik korban, dibuka sambil masuk sebagai penyerang.
        $tautan = app(EmailVerifier::class)->tautanBertandaTangan($korban);

        $this->actingAs($penyerang)->get($tautan)->assertRedirect(route('verification.notice'));

        $this->assertNull($korban->fresh()->email_verified_at);
        $this->assertNull($penyerang->fresh()->email_verified_at);
    }

    public function test_tautan_hangus_kalau_email_sudah_diganti(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $tautan = app(EmailVerifier::class)->tautanBertandaTangan($user);

        $user->forceFill(['email' => 'alamat-baru@example.com'])->save();

        $this->actingAs($user)->get($tautan)->assertRedirect(route('verification.notice'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /* ------------------------------------------------- Penjagaan slot lomba */

    public function test_slot_lomba_tertutup_sebelum_email_diverifikasi(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $kategori = RaceCategory::where('slug', '5k')->firstOrFail();

        $this->actingAs($user)->get('/pendaftaran/baru')
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)->post('/pendaftaran', [
            'race_category_id' => $kategori->id,
            'participant_name' => 'Rian', 'participant_email' => 'r@e.com',
            'participant_phone' => '0812', 'gender' => 'L',
            'birth_date' => '1996-08-17', 'city' => 'Gorontalo', 'jersey_size' => 'M',
            'emergency_name' => 'Ibu', 'emergency_phone' => '0813', 'agreement' => true,
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame(0, $user->registrations()->count());
    }

    public function test_slot_lomba_terbuka_setelah_verifikasi(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        app(EmailVerifier::class)->tandaiTerverifikasi($user);

        $this->actingAs($user)->get('/pendaftaran/baru')->assertOk();
    }

    public function test_panitia_tidak_pernah_tertahan_verifikasi(): void
    {
        // Akun pengelola dibuatkan developer, tidak pernah menerima email verifikasi.
        $panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
            'email_verified_at' => null,
        ]);

        $this->actingAs($panitia)->get('/panitia/pendaftaran')->assertOk();
    }

    public function test_akun_pengelola_baru_langsung_terverifikasi(): void
    {
        $developer = User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($developer)->post('/panitia/pengguna', [
            'name' => 'Panitia Baru', 'email' => 'baru@example.com',
            'role' => User::ROLE_PANITIA,
            'password' => 'rahasia12345', 'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasNoErrors();

        $baru = User::where('email', 'baru@example.com')->firstOrFail();

        // Kolomnya harus benar-benar tersimpan, bukan dibuang diam-diam oleh $fillable.
        $this->assertNotNull($baru->email_verified_at);
    }

    /* ---------------------------------------------------------- Isi email */

    public function test_email_verifikasi_memuat_kode_tombol_dan_tanda_tangan_panitia(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $tautan = 'https://contoh.test/verifikasi';

        $isi = (new VerifikasiEmail(nama: 'Rian', kode: '123456', tautan: $tautan))
            ->render();

        $this->assertStringContainsString('123456', $isi);
        $this->assertStringContainsString('Verifikasi Email Saya', $isi);
        $this->assertStringContainsString($tautan, $isi);
        // Tanda tangan panitia & penyelenggara ikut di badan email.
        $this->assertStringContainsString('Panitia', $isi);
        $this->assertStringContainsString('Ikatan Keluarga Alumni', $isi);
    }

    public function test_halaman_verifikasi_tidak_membocorkan_kode(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        $kode = app(EmailVerifier::class)->terbitkan($user)['kode'];

        // Kodenya hanya boleh ada di email, tidak pernah ikut terkirim ke browser.
        $this->actingAs($user)->get('/verifikasi-email')
            ->assertOk()
            ->assertDontSee($kode, false);
    }

    public function test_yang_sudah_terverifikasi_diarahkan_keluar_dari_halaman_verifikasi(): void
    {
        $user = $this->pesertaBelumVerifikasi();
        app(EmailVerifier::class)->tandaiTerverifikasi($user);

        $this->actingAs($user)->get('/verifikasi-email')->assertRedirect(route('dashboard'));
    }
}
