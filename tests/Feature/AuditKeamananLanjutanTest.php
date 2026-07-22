<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\News;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan audit lanjutan: celah yang lolos dari rangkaian tes sebelumnya.
 */
class AuditKeamananLanjutanTest extends TestCase
{
    use RefreshDatabase;

    private function peserta(array $ubah = []): User
    {
        return User::create(array_merge([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'phone' => '081200001111', 'email_verified_at' => now(),
        ], $ubah));
    }

    private function isiProfil(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Peserta',
            'email' => 'peserta@example.com',
            'phone' => '081200001111',
        ], $ubah);
    }

    /* ------------------------------- Ganti email mencabut verifikasi */

    public function test_ganti_email_mencabut_status_terverifikasi(): void
    {
        // Tanpa ini, satu alamat bisa diverifikasi lalu diganti ke alamat orang
        // lain sambil tetap dianggap terverifikasi — verifikasi jadi tidak ada
        // gunanya, dan kabar pembayaran bisa mendarat di kotak masuk orang asing.
        $user = $this->peserta();

        $this->actingAs($user)
            ->patch('/profil', $this->isiProfil(['email' => 'alamat-baru@example.com']))
            ->assertRedirect(route('verification.notice'));

        $user->refresh();

        $this->assertSame('alamat-baru@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_ganti_email_langsung_mengirim_kode_baru(): void
    {
        $user = $this->peserta();

        $this->actingAs($user)->patch('/profil', $this->isiProfil(['email' => 'alamat-baru@example.com']));

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_slot_lomba_ikut_tertutup_setelah_email_diganti(): void
    {
        $this->seed(RaceCategorySeeder::class);
        $user = $this->peserta();

        $this->actingAs($user)->get('/pendaftaran/baru')->assertOk();

        $this->actingAs($user)->patch('/profil', $this->isiProfil(['email' => 'alamat-baru@example.com']));

        $this->actingAs($user)->get('/pendaftaran/baru')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_menyimpan_profil_tanpa_mengganti_email_tidak_mencabut_verifikasi(): void
    {
        // Sekadar memperbaiki nama tidak boleh membuat peserta harus verifikasi ulang.
        $user = $this->peserta();

        $this->actingAs($user)
            ->patch('/profil', $this->isiProfil(['name' => 'Nama Diperbaiki']))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(0, EmailVerificationCode::count());
    }

    public function test_beda_huruf_besar_kecil_bukan_dianggap_ganti_email(): void
    {
        $user = $this->peserta();

        $this->actingAs($user)->patch('/profil', $this->isiProfil(['email' => 'PESERTA@example.com']));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_panitia_tidak_terlempar_ke_verifikasi_saat_ganti_email(): void
    {
        // Akun pengelola dibuatkan developer dan tidak pernah lewat verifikasi;
        // melemparnya ke halaman itu hanya akan mengunci panel dari dalam.
        $panitia = $this->peserta([
            'email' => 'panitia@example.com', 'role' => User::ROLE_PANITIA,
        ]);

        $this->actingAs($panitia)
            ->patch('/profil', $this->isiProfil(['email' => 'panitia-baru@example.com']))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($panitia->fresh()->email_verified_at);
    }

    /* ------------------------------------ Ganti sandi memutus sesi lain */

    public function test_ganti_kata_sandi_memutus_sesi_perangkat_lain(): void
    {
        $user = $this->peserta();

        $this->actingAs($user)
            ->put('/profil/kata-sandi', [
                'current_password' => 'rahasia12345',
                'password' => 'sandibaru12345',
                'password_confirmation' => 'sandibaru12345',
            ])
            ->assertSessionHasNoErrors();

        // Laravel menandai sesi dengan hash sandi saat logoutOtherDevices dipanggil.
        // Kalau penanda ini tidak ada, sesi penyusup tetap hidup setelah sandi diganti.
        $this->assertNotNull(session()->get('password_hash_web'));
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('sandibaru12345', $user->fresh()->password)
        );
    }

    public function test_sandi_lama_yang_salah_tetap_ditolak(): void
    {
        $user = $this->peserta();

        $this->actingAs($user)
            ->put('/profil/kata-sandi', [
                'current_password' => 'bukan-sandinya',
                'password' => 'sandibaru12345',
                'password_confirmation' => 'sandibaru12345',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('rahasia12345', $user->fresh()->password)
        );
    }

    /* ------------------------------------------- Komentar dipaginasi */

    public function test_komentar_berita_dipaginasi(): void
    {
        $panitia = $this->peserta(['email' => 'panitia@example.com', 'role' => User::ROLE_PANITIA]);

        $berita = News::create([
            'title' => 'Berita Ramai', 'slug' => 'berita-ramai',
            'body' => 'Isi berita.', 'author_id' => $panitia->id,
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);

        $penulis = $this->peserta();

        foreach (range(1, 45) as $i) {
            $berita->comments()->create(['user_id' => $penulis->id, 'body' => "Komentar ke-{$i}"]);
        }

        // Satu berita yang ramai tidak boleh mengirim seluruh komentarnya sekaligus.
        $this->get("/berita/{$berita->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('comments.data', 20)
                ->where('comments.total', 45));
    }

    /* ------------------------------ Pengaturan yang benar-benar dipakai */

    public function test_batas_waktu_bayar_mengikuti_pengaturan(): void
    {
        // Kolom ini bisa diubah developer di halaman Pengaturan Acara. Sebelumnya
        // nilainya diabaikan dan slot tetap dilepas mengikuti config — perubahan
        // yang tampak tersimpan tapi tidak berpengaruh apa pun.
        $this->seed(RaceCategorySeeder::class);
        \App\Models\Setting::simpan(['payment_deadline_hours' => '48']);

        $user = $this->peserta();
        $kategori = RaceCategory::where('slug', '5k')->firstOrFail();

        $this->actingAs($user)->post('/pendaftaran', [
            'race_category_id' => $kategori->id,
            'participant_name' => 'Rian', 'participant_email' => 'r@e.com',
            'participant_phone' => '0812', 'gender' => 'L',
            'birth_date' => '1996-08-17', 'city' => 'Gorontalo', 'jersey_size' => 'M',
            'emergency_name' => 'Ibu', 'emergency_phone' => '0813', 'agreement' => true,
        ]);

        $daftar = Registration::latest('id')->firstOrFail();

        $this->assertEqualsWithDelta(48, now()->diffInHours($daftar->expires_at), 1);
    }

    /* --------------------------------------------------- Zona waktu */

    public function test_aplikasi_memakai_zona_waktu_acara(): void
    {
        // Nilainya sempat dipaku 'UTC' di config/app.php sehingga APP_TIMEZONE
        // di .env diam-diam tidak berpengaruh. Akibatnya panitia yang mencentang
        // kehadiran pukul 05.30 WITA melihat panel mencatat 21.30 hari sebelumnya —
        // tepat di saat jam yang akurat paling dibutuhkan.
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $this->assertSame('Asia/Makassar', now()->timezone->getName());
    }

    public function test_jam_yang_dicatat_sama_dengan_jam_dinding_gorontalo(): void
    {
        $panitia = $this->peserta(['email' => 'panitia@example.com', 'role' => User::ROLE_PANITIA]);

        $jamDinding = now()->setTimezone('Asia/Makassar')->format('Y-m-d H:i');

        $this->actingAs($panitia);

        $this->assertSame($jamDinding, now()->format('Y-m-d H:i'));
    }

    public function test_flag_off_tetap_terbaca_pukul_enam_pagi(): void
    {
        // Tanggal acara disimpan dengan offset +08:00. Kalau zona aplikasinya
        // salah, hitung mundur di landing page meleset delapan jam.
        $this->assertSame(
            '06:00',
            \Illuminate\Support\Carbon::parse(config('funrun.event_date'))->format('H:i')
        );
    }

    /* --------------------------------------------- Tajuk keamanan */

    public function test_tajuk_keamanan_terpasang_di_halaman_publik(): void
    {
        $respon = $this->get('/');

        $respon->assertHeader('X-Content-Type-Options', 'nosniff');
        $respon->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $respon->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $respon->headers->get('Content-Security-Policy');

        // Yang paling menentukan: skrip asing tidak boleh bisa dijalankan.
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    /* ------------------------------- Data pribadi tidak bocor ke publik */

    public function test_halaman_berita_publik_tidak_membocorkan_email_pengomentar(): void
    {
        $panitia = $this->peserta(['email' => 'panitia@example.com', 'role' => User::ROLE_PANITIA]);
        $pengomentar = $this->peserta(['email' => 'rahasia-peserta@example.com']);

        $berita = News::create([
            'title' => 'Berita', 'slug' => 'berita',
            'body' => 'Isi.', 'author_id' => $panitia->id,
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        $berita->comments()->create(['user_id' => $pengomentar->id, 'body' => 'Halo semua']);

        $this->get('/berita/berita')
            ->assertOk()
            ->assertDontSee('rahasia-peserta@example.com', false);
    }

    public function test_papan_hasil_publik_tidak_membocorkan_kontak_peserta(): void
    {
        $this->seed(RaceCategorySeeder::class);
        $user = $this->peserta();

        Registration::create([
            'registration_code' => 'GFR-5K-0001',
            'user_id' => $user->id,
            'race_category_id' => RaceCategory::where('slug', '5k')->first()->id,
            'participant_name' => 'Pelari Uji',
            'participant_email' => 'kontak-rahasia@example.com',
            'participant_phone' => '081299998888',
            'gender' => 'L', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '081277776666',
            'amount' => 100000, 'status' => Registration::STATUS_CONFIRMED,
            'bib_number' => '5001', 'finish_seconds' => 1800,
        ]);

        $respon = $this->get('/hasil');

        $respon->assertOk();
        $respon->assertDontSee('kontak-rahasia@example.com', false);
        $respon->assertDontSee('081299998888', false);
        // Kontak darurat sama pribadinya — tidak ada urusannya dengan papan hasil.
        $respon->assertDontSee('081277776666', false);
    }
}
