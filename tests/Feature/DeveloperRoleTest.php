<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $panitia;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);

        $this->developer = User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
        ]);
        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
    }

    /* ------------------------------------------------------- Hak akses */

    /**
     * Ditelusuri dari daftar rute yang benar-benar terdaftar, bukan daftar URL
     * yang ditulis tangan. Versi lama hanya memeriksa 5 dari 15 halaman, jadi
     * menu yang ditambahkan belakangan tidak pernah ikut teruji — dan justru
     * menu barulah yang paling mudah lupa dibukakan untuk developer.
     */
    public function test_developer_bisa_membuka_semua_halaman_panitia(): void
    {
        $halaman = $this->halamanPanitia();

        // Penjaga: kalau jumlahnya anjlok, berarti penelusurannya yang rusak,
        // bukan aplikasinya yang lolos.
        $this->assertGreaterThanOrEqual(12, count($halaman));

        foreach ($halaman as $url) {
            $this->actingAs($this->developer)
                ->get($url)
                ->assertOk("Developer tertahan di {$url}");
        }
    }

    public function test_setiap_halaman_panitia_tertutup_untuk_peserta(): void
    {
        // Sisi sebaliknya: membuka akses developer tidak boleh ikut membocorkan
        // satu pun halaman ini ke peserta biasa.
        foreach ($this->halamanPanitia() as $url) {
            $this->actingAs($this->peserta)
                ->get($url)
                ->assertForbidden("Peserta bisa membuka {$url}");
        }
    }

    /**
     * Semua rute GET di grup panitia yang tidak butuh parameter.
     *
     * @return list<string>
     */
    private function halamanPanitia(): array
    {
        return collect(app('router')->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_starts_with($uri, 'panitia'))
            // Rute berparameter dan lembar cetak butuh data khusus; keduanya
            // sudah punya pengujian tersendiri.
            ->reject(fn (string $uri) => str_contains($uri, '{') || str_contains($uri, 'lembar'))
            ->map(fn (string $uri) => '/'.$uri)
            ->values()
            ->all();
    }

    public function test_halaman_developer_ditolak_untuk_panitia_biasa(): void
    {
        foreach (['/panitia/pengguna', '/panitia/pengaturan'] as $url) {
            $this->actingAs($this->panitia)->get($url)->assertForbidden();
        }

        $this->actingAs($this->panitia)
            ->post('/panitia/pengguna', ['name' => 'Nakal'])
            ->assertForbidden();

        $this->actingAs($this->panitia)
            ->patch('/panitia/pengaturan', ['event_name' => 'Diubah'])
            ->assertForbidden();
    }

    public function test_peserta_tidak_bisa_menyentuh_halaman_developer(): void
    {
        $this->actingAs($this->peserta)->get('/panitia/pengguna')->assertForbidden();
        $this->actingAs($this->peserta)->get('/panitia/pengaturan')->assertForbidden();
    }

    public function test_developer_diarahkan_ke_panel_setelah_masuk(): void
    {
        $this->post('/masuk', [
            'email' => $this->developer->email,
            'password' => 'rahasia12345',
        ])->assertRedirect('/panitia');
    }

    /* --------------------------------------------------- Kelola akun */

    public function test_developer_bisa_membuat_akun_panitia(): void
    {
        $this->actingAs($this->developer)->post('/panitia/pengguna', [
            'name' => 'Panitia Baru',
            'email' => 'baru@example.com',
            'phone' => '0812',
            'role' => User::ROLE_PANITIA,
            'password' => 'sandibaru12345',
            'password_confirmation' => 'sandibaru12345',
        ])->assertRedirect();

        $baru = User::where('email', 'baru@example.com')->first();

        $this->assertNotNull($baru);
        $this->assertSame(User::ROLE_PANITIA, $baru->role);
        // Kata sandinya harus tersimpan dalam bentuk hash, bukan teks polos.
        $this->assertNotSame('sandibaru12345', $baru->password);
        $this->assertTrue(auth()->validate(['email' => 'baru@example.com', 'password' => 'sandibaru12345']));
    }

    public function test_mengubah_akun_tanpa_mengisi_sandi_tidak_mengganti_sandi_lama(): void
    {
        $this->actingAs($this->developer)->patch("/panitia/pengguna/{$this->panitia->id}", [
            'name' => 'Panitia Berubah',
            'email' => $this->panitia->email,
            'role' => User::ROLE_PANITIA,
            'password' => '',
        ])->assertRedirect();

        $this->assertSame('Panitia Berubah', $this->panitia->fresh()->name);
        $this->assertTrue(auth()->validate([
            'email' => $this->panitia->email,
            'password' => 'rahasia12345',
        ]));
    }

    public function test_developer_terakhir_tidak_bisa_menurunkan_perannya_sendiri(): void
    {
        $this->actingAs($this->developer)->patch("/panitia/pengguna/{$this->developer->id}", [
            'name' => 'Dev',
            'email' => $this->developer->email,
            'role' => User::ROLE_PANITIA,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($this->developer->fresh()->isDeveloper());
    }

    public function test_developer_terakhir_tidak_bisa_dihapus(): void
    {
        $lain = User::create([
            'name' => 'Dev Dua', 'email' => 'dev2@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
        ]);

        // Masih ada dua developer, jadi yang ini boleh dihapus.
        $this->actingAs($this->developer)->delete("/panitia/pengguna/{$lain->id}")->assertRedirect();
        $this->assertSame(1, User::where('role', User::ROLE_DEVELOPER)->count());

        // Sekarang tinggal satu — dan tidak bisa menghapus dirinya sendiri.
        $this->actingAs($this->developer)
            ->delete("/panitia/pengguna/{$this->developer->id}")
            ->assertSessionHasErrors('user');
    }

    public function test_akun_peserta_tidak_bisa_disentuh_dari_halaman_pengguna(): void
    {
        $this->actingAs($this->developer)
            ->patch("/panitia/pengguna/{$this->peserta->id}", [
                'name' => 'Diretas', 'email' => $this->peserta->email, 'role' => User::ROLE_DEVELOPER,
            ])
            ->assertNotFound();

        $this->assertSame(User::ROLE_PESERTA, $this->peserta->fresh()->role);
    }

    public function test_daftar_pengguna_hanya_memuat_pengelola(): void
    {
        $this->actingAs($this->developer)
            ->get('/panitia/pengguna')
            ->assertInertia(fn ($page) => $page
                ->component('Panitia/Users')
                ->has('users.data', 2)     // developer + panitia, tanpa peserta
                ->where('pesertaCount', 1));
    }

    /* ------------------------------------------------ Pengaturan acara */

    public function test_developer_bisa_menyimpan_pengaturan_acara(): void
    {
        $this->actingAs($this->developer)->patch('/panitia/pengaturan', [
            'event_name' => 'Gong Fun Run 2027',
            'event_date' => '2027-10-30T06:00:00',
            'location' => 'Lapangan Baru',
            'payment_bank' => 'BRI',
            'payment_account' => '123456789',
            'payment_holder' => 'Panitia IKA',
            'payment_whatsapp' => '081200001111',
            'payment_deadline_hours' => 48,
            'registration_open' => true,
            'wa_enabled' => false,
            'mail_enabled' => false,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Gong Fun Run 2027', Setting::ambil('event_name'));
        $this->assertSame('BRI', Setting::ambil('payment_bank'));
        $this->assertSame('48', Setting::ambil('payment_deadline_hours'));
    }

    public function test_pengaturan_memakai_nilai_bawaan_selama_belum_pernah_disimpan(): void
    {
        $this->assertSame(0, Setting::count());

        // Situs tetap punya nilai walau tabelnya masih kosong.
        // Diperiksa 'tidak kosong', bukan sekadar 'tidak null': nilai bawaan yang
        // gagal diambil dari config muncul sebagai string kosong, bukan null —
        // dan itu persis bug yang pernah lolos di sini.
        foreach (['event_date', 'location', 'payment_bank', 'payment_account', 'payment_holder', 'payment_whatsapp'] as $key) {
            $this->assertNotEmpty(Setting::ambil($key), "Nilai bawaan {$key} kosong.");
        }

        $this->assertSame('Bank BRI', Setting::ambil('payment_bank'));
    }

    public function test_tanggal_acara_tampil_dalam_format_yang_dimengerti_input(): void
    {
        // <input type="datetime-local"> hanya menerima YYYY-MM-DDTHH:MM. Nilai
        // dengan offset zona atau spasi ditampilkan sebagai isian kosong, dan
        // karena kolomnya wajib, seluruh formulir jadi tidak bisa dikirim.
        $this->actingAs($this->developer)->get('/panitia/pengaturan')
            ->assertInertia(fn ($page) => $page->where(
                'settings.event_date',
                fn ($nilai) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $nilai)
            ));
    }

    public function test_tanggal_tersimpan_dengan_spasi_tetap_tampil_benar(): void
    {
        Setting::simpan(['event_date' => '2027-01-05 07:30:00']);

        $this->actingAs($this->developer)->get('/panitia/pengaturan')
            ->assertInertia(fn ($page) => $page->where('settings.event_date', '2027-01-05T07:30'));
    }

    public function test_pengaturan_menolak_nilai_yang_tidak_masuk_akal(): void
    {
        $this->actingAs($this->developer)->patch('/panitia/pengaturan', [
            'event_name' => '',
            'event_date' => 'bukan-tanggal',
            'location' => 'Lapangan',
            'payment_bank' => 'BRI',
            'payment_account' => '123',
            'payment_holder' => 'Panitia',
            'payment_whatsapp' => '0812',
            'payment_deadline_hours' => 0,
            'registration_open' => true,
            'wa_enabled' => false,
        ])->assertSessionHasErrors(['event_name', 'event_date', 'payment_deadline_hours']);
    }
}
