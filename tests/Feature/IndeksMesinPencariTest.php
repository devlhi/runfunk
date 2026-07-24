<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apa yang boleh dan tidak boleh masuk mesin pencari.
 *
 * Kegagalan di berkas ini berarti data orang bisa bocor ke hasil pencarian —
 * jadi diperiksa dua-duanya: yang harus tertutup, dan yang harus tetap terbuka.
 */
class IndeksMesinPencariTest extends TestCase
{
    use RefreshDatabase;

    private function pengelola(): User
    {
        return User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
            'email_verified_at' => now(),
        ]);
    }

    private function peserta(): User
    {
        return User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => now(),
        ]);
    }

    /* ------------------------------------ Yang HARUS tertutup dari mesin pencari */

    public function test_halaman_berisi_data_orang_menolak_diindeks(): void
    {
        $this->seed(RaceCategorySeeder::class);
        $user = $this->peserta();

        $daftar = Registration::create([
            'registration_code' => 'GFR-5K-0001',
            'user_id' => $user->id,
            'race_category_id' => RaceCategory::where('slug', '5k')->first()->id,
            'participant_name' => 'Rian Pelari', 'participant_email' => 'rian@example.com',
            'participant_phone' => '0812', 'gender' => 'L',
            'birth_date' => '1996-08-17', 'city' => 'Gorontalo', 'jersey_size' => 'M',
            'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'amount' => 100000, 'status' => Registration::STATUS_CONFIRMED,
            'bib_number' => '5001',
        ]);

        // Kolom hasil lomba sengaja tidak masuk $fillable — panitia menulisnya
        // lewat forceFill di ResultController, supaya waktu finis tidak bisa
        // ikut terpasang dari isian formulir mana pun.
        $daftar->forceFill(['finish_seconds' => 1800])->save();

        // Yang diperiksa adalah keadaan saat halamannya BENAR-BENAR memuat data —
        // bukan saat tamu dialihkan ke halaman masuk.
        foreach (["/dashboard", "/pendaftaran/{$daftar->id}", "/sertifikat/{$daftar->id}", '/profil'] as $jalur) {
            $this->actingAs($user)
                ->get($jalur)
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    public function test_seluruh_panel_panitia_menolak_diindeks(): void
    {
        $dev = $this->pengelola();
        $this->seed(RaceCategorySeeder::class);

        foreach (['/panitia', '/panitia/pendaftaran', '/panitia/laporan', '/panitia/pengaturan'] as $jalur) {
            $this->actingAs($dev)
                ->get($jalur)
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    public function test_halaman_masuk_dan_daftar_tidak_diindeks(): void
    {
        // Orang datang ke sini lewat tombol di halaman depan, bukan lewat Google.
        foreach (['/masuk', '/daftar-akun', '/lupa-kata-sandi'] as $jalur) {
            $this->get($jalur)
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    public function test_tag_meta_ikut_menandai_halaman_privat(): void
    {
        // Sebagian perayap hanya membaca tag <meta>, bukan tajuk HTTP.
        $this->get('/masuk')->assertSee('name="robots" content="noindex, nofollow, noarchive"', false);
    }

    /* -------------------------------- Yang HARUS tetap terbuka untuk mesin pencari */

    public function test_halaman_umum_boleh_diindeks(): void
    {
        foreach (['/', '/berita', '/hasil'] as $jalur) {
            $respon = $this->get($jalur);

            $respon->assertOk();
            $this->assertNull(
                $respon->headers->get('X-Robots-Tag'),
                "Halaman umum {$jalur} malah ditandai noindex."
            );
            $respon->assertSee('name="robots" content="index, follow', false);
        }
    }

    /* --------------------------------------------------------- robots.txt */

    public function test_robots_melarang_seluruh_jalur_berisi_data_orang(): void
    {
        $isi = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->getContent();

        foreach (['/panitia', '/dashboard', '/pendaftaran', '/sertifikat', '/bukti-bayar', '/profil'] as $jalur) {
            $this->assertStringContainsString("Disallow: {$jalur}", $isi);
        }
    }

    public function test_robots_menunjuk_sitemap_dan_membuka_halaman_umum(): void
    {
        $isi = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $isi);
        $this->assertStringContainsString('Allow: /berita', $isi);
        $this->assertStringContainsString('Allow: /hasil', $isi);
    }

    public function test_robots_tidak_lagi_mengizinkan_semuanya(): void
    {
        // Isi bawaan Laravel adalah "Disallow:" tanpa nilai — yang justru berarti
        // MENGIZINKAN semuanya, termasuk panel panitia dan sertifikat peserta.
        $this->assertStringNotContainsString(
            "Disallow:\n",
            $this->get('/robots.txt')->getContent()
        );
    }

    /* --------------------------------------------------------- sitemap.xml */

    public function test_sitemap_memuat_halaman_umum_saja(): void
    {
        $isi = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $isi);
        $this->assertStringContainsString('<loc>'.url('/berita').'</loc>', $isi);

        foreach (['/panitia', '/dashboard', '/sertifikat', '/bukti-bayar'] as $jalur) {
            $this->assertStringNotContainsString($jalur, $isi);
        }
    }

    public function test_sitemap_memuat_berita_yang_sudah_tayang_saja(): void
    {
        $penulis = $this->pengelola();

        News::create([
            'title' => 'Berita Tayang', 'slug' => 'berita-tayang', 'body' => 'Isi.',
            'author_id' => $penulis->id, 'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        News::create([
            'title' => 'Masih Draf', 'slug' => 'masih-draf', 'body' => 'Isi.',
            'author_id' => $penulis->id, 'is_published' => false,
        ]);

        $isi = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('berita-tayang', $isi);
        // Draf belum boleh diketahui siapa pun, apalagi diantre untuk dirayapi.
        $this->assertStringNotContainsString('masih-draf', $isi);
    }

    /* --------------------------------------------------- Pratinjau saat dibagikan */

    public function test_beranda_membawa_open_graph_untuk_berbagi_tautan(): void
    {
        // Acara seperti ini menyebar lewat WhatsApp, bukan mesin pencari.
        $respon = $this->get('/');

        $respon->assertSee('property="og:title"', false);
        $respon->assertSee('property="og:description"', false);
        $respon->assertSee('property="og:image"', false);
        $respon->assertSee(asset('images/hero-runners.jpg'), false);
    }

    public function test_beranda_membawa_data_terstruktur_acara(): void
    {
        $respon = $this->get('/');

        $respon->assertSee('application/ld+json', false);
        $respon->assertSee('SportsEvent', false);
        $respon->assertSee('Kabupaten Gorontalo', false);
    }

    public function test_data_terstruktur_hanya_di_beranda(): void
    {
        // Kalau dipasang di tiap halaman, Google membacanya sebagai banyak acara
        // berbeda dengan nama yang sama.
        $this->get('/berita')->assertDontSee('application/ld+json', false);
        $this->get('/hasil')->assertDontSee('application/ld+json', false);
    }

    public function test_data_terstruktur_lengkap_untuk_search_console(): void
    {
        // Search Console menandai tiga kolom hilang: offers, endDate, performer.
        // Ketiganya kini wajib ada, kalau tidak peringatannya balik lagi.
        $this->seed(RaceCategorySeeder::class);

        $html = $this->get('/')->getContent();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data, 'Data terstruktur beranda bukan JSON yang sah.');
        $this->assertArrayHasKey('endDate', $data);
        $this->assertArrayHasKey('performer', $data);
        $this->assertArrayHasKey('offers', $data);

        // Acara tidak boleh selesai sebelum dimulai.
        $this->assertGreaterThan($data['startDate'], $data['endDate']);

        // Tiap penawaran wajib punya harga angka murni dan mata uang — tanpa itu
        // Google tetap menandai "offers" kurang lengkap.
        $this->assertNotEmpty($data['offers']);
        foreach ($data['offers'] as $offer) {
            $this->assertSame('IDR', $offer['priceCurrency']);
            $this->assertMatchesRegularExpression('/^\d+$/', $offer['price']);
        }
    }

    /* ---------------------------------- Meta per-artikel untuk perayap */

    public function test_artikel_berita_membawa_meta_sendiri_di_html_awal(): void
    {
        // WhatsApp, Facebook, dan ambilan awal Google tidak menjalankan JS, jadi
        // meta artikel WAJIB ada di HTML server — bukan cuma dipasang Inertia di
        // sisi klien. Kalau tidak, tiap berita yang dibagikan tampil sebagai
        // kartu acara yang sama.
        $penulis = $this->pengelola();
        $berita = News::create([
            'title' => 'Rute Baru Diumumkan', 'slug' => 'rute-baru-diumumkan',
            'excerpt' => 'Panitia merilis peta rute resmi 5K dan 10K.',
            'body' => 'Isi lengkap berita.', 'author_id' => $penulis->id,
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);

        $html = $this->get('/berita/'.$berita->slug)->getContent();

        $this->assertStringContainsString('<title inertia>Rute Baru Diumumkan</title>', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        $this->assertStringContainsString('content="Rute Baru Diumumkan"', $html);
        $this->assertStringContainsString('Panitia merilis peta rute resmi 5K dan 10K.', $html);
    }

    public function test_beranda_tetap_memakai_meta_acara(): void
    {
        // Perbaikan meta berita tidak boleh bocor ke beranda.
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('property="og:type" content="website"', $html);
        // "&" ter-escape jadi "&amp;" di atribut HTML — itu memang benar.
        $this->assertStringContainsString('Fun Run 5K &amp; 10K Gorontalo', $html);
    }

    /* ------------------------------------------ Verifikasi Search Console */

    public function test_kode_verifikasi_google_muncul_di_kepala_halaman(): void
    {
        \App\Models\Setting::simpan(['google_verification' => 'kode-uji-123']);

        $this->get('/')->assertSee(
            '<meta name="google-site-verification" content="kode-uji-123">',
            false
        );
    }

    public function test_seluruh_tag_meta_dari_google_diambil_kodenya_saja(): void
    {
        // Developer sering menempel seluruh tag dari Search Console, bukan kodenya
        // saja. Hasilnya harus tetap satu tag yang benar, bukan tag di dalam tag.
        \App\Models\Setting::simpan([
            'google_verification' => '<meta name="google-site-verification" content="abc123xyz" />',
        ]);

        $this->get('/')
            ->assertSee('content="abc123xyz"', false)
            ->assertDontSee('content="<meta', false);
    }

    public function test_tanpa_kode_tidak_ada_tag_verifikasi(): void
    {
        $this->get('/')->assertDontSee('google-site-verification', false);
    }
}
