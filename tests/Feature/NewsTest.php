<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\NewsComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private User $panitia;

    private User $peserta;

    private User $pesertaLain;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->panitia = User::create([
            'name' => 'Panitia', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
        $this->pesertaLain = User::create([
            'name' => 'Peserta Lain', 'email' => 'lain@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
    }

    private function buatBerita(array $override = []): News
    {
        return News::create([
            'title' => $override['title'] ?? 'Rute 10K diumumkan',
            'slug' => News::buatSlug($override['title'] ?? 'Rute 10K diumumkan'),
            'body' => $override['body'] ?? 'Isi berita lengkap.',
            'excerpt' => $override['excerpt'] ?? null,
            'is_published' => $override['is_published'] ?? true,
            'published_at' => $override['published_at'] ?? now()->subHour(),
            'created_by' => $this->panitia->id,
        ]);
    }

    /* ------------------------------------------------- Halaman publik */

    public function test_daftar_berita_terbuka_untuk_umum(): void
    {
        $this->buatBerita();

        $this->get('/berita')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('News/Index')->has('news.data', 1));
    }

    public function test_berita_draf_tidak_muncul_untuk_umum(): void
    {
        $this->buatBerita(['title' => 'Terbit']);
        $this->buatBerita(['title' => 'Draf', 'is_published' => false]);

        $this->get('/berita')
            ->assertInertia(fn ($p) => $p->has('news.data', 1)->where('news.data.0.title', 'Terbit'));
    }

    public function test_berita_draf_menghasilkan_404_untuk_tamu_tapi_terbuka_bagi_panitia(): void
    {
        $draf = $this->buatBerita(['is_published' => false]);

        $this->get("/berita/{$draf->slug}")->assertNotFound();
        $this->actingAs($this->peserta)->get("/berita/{$draf->slug}")->assertNotFound();
        $this->actingAs($this->panitia)->get("/berita/{$draf->slug}")->assertOk();
    }

    public function test_berita_berjadwal_masa_depan_belum_bisa_dibuka(): void
    {
        $nanti = $this->buatBerita(['published_at' => now()->addDay()]);

        $this->get("/berita/{$nanti->slug}")->assertNotFound();
        $this->get('/berita')->assertInertia(fn ($p) => $p->has('news.data', 0));
    }

    public function test_membuka_berita_menaikkan_penghitung_baca(): void
    {
        $berita = $this->buatBerita();
        $sebelum = $berita->updated_at;

        $this->get("/berita/{$berita->slug}")->assertOk();

        $berita->refresh();
        $this->assertSame(1, $berita->views);
        // Membaca tidak boleh mengubah urutan daftar berita.
        $this->assertEquals($sebelum->timestamp, $berita->updated_at->timestamp);
    }

    /* ------------------------------------------------------- Komentar */

    public function test_tamu_tidak_bisa_berkomentar(): void
    {
        $berita = $this->buatBerita();

        $this->post("/berita/{$berita->slug}/komentar", ['body' => 'Halo'])
            ->assertRedirect('/masuk');

        $this->assertSame(0, NewsComment::count());
    }

    public function test_peserta_yang_masuk_bisa_berkomentar(): void
    {
        $berita = $this->buatBerita();

        $this->actingAs($this->peserta)
            ->post("/berita/{$berita->slug}/komentar", ['body' => 'Semangat panitia!'])
            ->assertRedirect();

        $this->assertSame('Semangat panitia!', NewsComment::first()->body);
    }

    public function test_komentar_kosong_dan_kepanjangan_ditolak(): void
    {
        $berita = $this->buatBerita();

        $this->actingAs($this->peserta)
            ->post("/berita/{$berita->slug}/komentar", ['body' => '  '])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->peserta)
            ->post("/berita/{$berita->slug}/komentar", ['body' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, NewsComment::count());
    }

    public function test_komentar_di_berita_draf_ditolak(): void
    {
        $draf = $this->buatBerita(['is_published' => false]);

        $this->actingAs($this->peserta)
            ->post("/berita/{$draf->slug}/komentar", ['body' => 'Bocoran'])
            ->assertNotFound();
    }

    public function test_komentar_hanya_bisa_dihapus_penulisnya_atau_panitia(): void
    {
        $berita = $this->buatBerita();
        $komentar = $berita->comments()->create(['user_id' => $this->peserta->id, 'body' => 'Punya saya']);

        // Peserta lain tidak boleh menghapus komentar orang.
        $this->actingAs($this->pesertaLain)
            ->delete("/komentar/{$komentar->id}")
            ->assertForbidden();
        $this->assertSame(1, NewsComment::count());

        // Penulisnya sendiri boleh.
        $this->actingAs($this->peserta)->delete("/komentar/{$komentar->id}")->assertRedirect();
        $this->assertSame(0, NewsComment::count());
    }

    public function test_panitia_bisa_menghapus_komentar_siapa_pun(): void
    {
        $berita = $this->buatBerita();
        $komentar = $berita->comments()->create(['user_id' => $this->peserta->id, 'body' => 'Spam']);

        $this->actingAs($this->panitia)->delete("/komentar/{$komentar->id}")->assertRedirect();
        $this->assertSame(0, NewsComment::count());
    }

    /**
     * Props Inertia dirender di dalam blok <script type="application/json">.
     * Komentar yang memuat </script> karena itu berpotensi membobol keluar dari
     * blok tersebut dan menjalankan skrip milik penyerang. Tes ini memastikan
     * urutan penutup itu selalu keluar dalam bentuk ter-escape.
     */
    public function test_komentar_tidak_bisa_membobol_keluar_blok_script(): void
    {
        $berita = $this->buatBerita();
        $jahat = '</script><script>alert(document.cookie)</script>';

        $this->actingAs($this->peserta)
            ->post("/berita/{$berita->slug}/komentar", ['body' => $jahat]);

        // Disimpan mentah — tidak pernah diproses sebagai HTML di mana pun.
        $this->assertSame($jahat, NewsComment::first()->body);

        $html = $this->get("/berita/{$berita->slug}")->assertOk()->getContent();

        // Yang berbahaya adalah tag penutup mentah; bentuk ter-escape aman.
        $this->assertStringNotContainsString('</script><script>alert', $html);
        $this->assertStringContainsString('<\/script>', $html);
    }

    public function test_judul_berita_juga_tidak_bisa_menyuntik_skrip(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => '</script><script>alert(1)</script>',
            'body' => 'Isi',
            'is_published' => true,
        ]);

        $berita = News::first();
        $html = $this->get("/berita/{$berita->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert', $html);
    }

    /* --------------------------------------------------- Kelola berita */

    public function test_peserta_tidak_bisa_menyentuh_kelola_berita(): void
    {
        $berita = $this->buatBerita();

        $this->actingAs($this->peserta)->get('/panitia/berita')->assertForbidden();
        $this->actingAs($this->peserta)->post('/panitia/berita', ['title' => 'Nakal'])->assertForbidden();
        $this->actingAs($this->peserta)->delete("/panitia/berita/{$berita->id}")->assertForbidden();

        $this->assertSame(1, News::count());
    }

    public function test_panitia_bisa_menerbitkan_berita_dengan_sampul(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => 'Race pack sudah siap',
            'excerpt' => 'Jersey dan medali sudah tiba.',
            'body' => 'Isi lengkap berita.',
            'is_published' => true,
            'cover' => UploadedFile::fake()->image('sampul.jpg', 1200, 675),
        ])->assertRedirect();

        $berita = News::first();

        $this->assertSame('race-pack-sudah-siap', $berita->slug);
        $this->assertNotNull($berita->cover_path);
        Storage::disk('public')->assertExists($berita->cover_path);
    }

    public function test_skrip_yang_disamarkan_sebagai_gambar_ditolak(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'shell').'.jpg';
        file_put_contents($path, '<?php system($_GET["c"]); ?>');

        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => 'Berita', 'body' => 'Isi',
            'is_published' => true,
            'cover' => new UploadedFile($path, 'sampul.jpg', null, null, true),
        ])->assertSessionHasErrors('cover');

        $this->assertSame(0, News::count());
        @unlink($path);
    }

    public function test_judul_kembar_menghasilkan_slug_berbeda(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => 'Kabar Terbaru', 'body' => 'Satu', 'is_published' => true,
        ]);
        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => 'Kabar Terbaru', 'body' => 'Dua', 'is_published' => true,
        ]);

        $slugs = News::pluck('slug')->all();

        $this->assertSame(['kabar-terbaru', 'kabar-terbaru-2'], $slugs);
        $this->assertCount(2, array_unique($slugs));
    }

    /**
     * Tanpa paginasi, halaman kelola mengirim seluruh berita lengkap dengan isi
     * penuh tiap artikel — pada 100 berita ukurannya menembus 239 KB.
     */
    public function test_daftar_kelola_berita_dipaginasi(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->buatBerita(['title' => "Berita ke-{$i}"]);
        }

        $this->actingAs($this->panitia)
            ->get('/panitia/berita')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('news.data', 10)
                ->where('news.total', 25)
                ->where('news.last_page', 3));
    }

    public function test_daftar_berita_publik_juga_dipaginasi(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->buatBerita(['title' => "Publik ke-{$i}"]);
        }

        $this->get('/berita')
            ->assertInertia(fn ($p) => $p->has('news.data', 9)->where('news.total', 20));
    }

    public function test_landing_hanya_memuat_lima_berita_terbaru(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->buatBerita([
                'title' => "Landing ke-{$i}",
                'published_at' => now()->subDays(12 - $i),
            ]);
        }

        $this->get('/')->assertInertia(fn ($p) => $p
            ->has('news', 5)
            // Urutannya dari yang paling baru.
            ->where('news.0.title', 'Landing ke-12')
            ->where('news.4.title', 'Landing ke-8'));
    }

    public function test_berita_draf_tidak_bocor_ke_landing(): void
    {
        $this->buatBerita(['title' => 'Terbit']);
        $this->buatBerita(['title' => 'Draf', 'is_published' => false]);
        $this->buatBerita(['title' => 'Terjadwal', 'published_at' => now()->addWeek()]);

        $this->get('/')->assertInertia(fn ($p) => $p
            ->has('news', 1)
            ->where('news.0.title', 'Terbit'));
    }

    public function test_menghapus_berita_ikut_menghapus_komentar_dan_sampulnya(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => 'Akan dihapus', 'body' => 'Isi', 'is_published' => true,
            'cover' => UploadedFile::fake()->image('sampul.jpg'),
        ]);

        $berita = News::first();
        $sampul = $berita->cover_path;
        $berita->comments()->create(['user_id' => $this->peserta->id, 'body' => 'Komentar']);

        $this->actingAs($this->panitia)->delete("/panitia/berita/{$berita->id}")->assertRedirect();

        $this->assertSame(0, News::count());
        $this->assertSame(0, NewsComment::count());
        Storage::disk('public')->assertMissing($sampul);
    }

    public function test_mengubah_berita_tanpa_sampul_baru_mempertahankan_sampul_lama(): void
    {
        $this->actingAs($this->panitia)->post('/panitia/berita', [
            'title' => 'Awal', 'body' => 'Isi', 'is_published' => true,
            'cover' => UploadedFile::fake()->image('sampul.jpg'),
        ]);

        $berita = News::first();
        $sampulLama = $berita->cover_path;

        $this->actingAs($this->panitia)->post("/panitia/berita/{$berita->id}", [
            '_method' => 'post',
            'title' => 'Judul Diubah', 'body' => 'Isi baru', 'is_published' => true,
        ])->assertRedirect();

        $berita->refresh();

        $this->assertSame('Judul Diubah', $berita->title);
        $this->assertSame($sampulLama, $berita->cover_path);
        Storage::disk('public')->assertExists($sampulLama);
    }
}
