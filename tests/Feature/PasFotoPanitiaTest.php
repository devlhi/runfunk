<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pas foto pada kartu panitia.
 */
class PasFotoPanitiaTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $panitia;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->developer = User::create([
            'name' => 'Dev', 'email' => 'dev@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_DEVELOPER,
            'email_verified_at' => now(),
        ]);
        $this->panitia = User::create([
            'name' => 'Bendahara IKA', 'email' => 'panitia@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PANITIA,
            'email_verified_at' => now(),
        ]);
        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA,
            'email_verified_at' => now(),
        ]);
    }

    /** Berkas sungguhan, supaya pemeriksaan isi (mimetypes) benar-benar berjalan. */
    private function fotoAsli(string $nama = 'pasfoto.jpg'): UploadedFile
    {
        $jalur = sys_get_temp_dir().'/'.uniqid('pf').'.jpg';
        $im = imagecreatetruecolor(300, 400);
        imagejpeg($im, $jalur, 80);
        imagedestroy($im);

        return new UploadedFile($jalur, $nama, 'image/jpeg', null, true);
    }

    private function unggah(?UploadedFile $berkas = null, ?User $ke = null, ?User $sebagai = null)
    {
        return $this->actingAs($sebagai ?? $this->developer)
            ->post('/panitia/kartu-panitia/'.($ke ?? $this->panitia)->id.'/foto', [
                'foto' => $berkas ?? $this->fotoAsli(),
            ]);
    }

    /* ------------------------------------------------------- Jalur normal */

    public function test_developer_bisa_mengunggah_pas_foto(): void
    {
        $this->unggah()->assertSessionHasNoErrors();

        $this->panitia->refresh();

        $this->assertNotNull($this->panitia->photo_path);
        Storage::disk('local')->assertExists($this->panitia->photo_path);
    }

    public function test_foto_disimpan_di_disk_privat_bukan_folder_publik(): void
    {
        // Ini foto wajah orang. Tidak ada alasan siapa pun di internet bisa
        // mengunduhnya lewat tautan tebakan.
        $this->unggah();

        $jalur = $this->panitia->fresh()->photo_path;

        $this->assertStringNotContainsString('public', $jalur);
        $this->assertFalse(file_exists(public_path('storage/'.$jalur)));
    }

    public function test_mengganti_foto_membuang_berkas_lama(): void
    {
        $this->unggah();
        $lama = $this->panitia->fresh()->photo_path;

        $this->unggah();
        $baru = $this->panitia->fresh()->photo_path;

        $this->assertNotSame($lama, $baru);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);
    }

    public function test_foto_bisa_dihapus_dan_kartu_kembali_ke_bingkai_tempel(): void
    {
        $this->unggah();
        $jalur = $this->panitia->fresh()->photo_path;

        $this->actingAs($this->developer)
            ->delete("/panitia/kartu-panitia/{$this->panitia->id}/foto")
            ->assertSessionHasNoErrors();

        $this->assertNull($this->panitia->fresh()->photo_path);
        Storage::disk('local')->assertMissing($jalur);
    }

    /* ------------------------------------------------ Berkas yang ditolak */

    public function test_berkas_yang_menyamar_sebagai_gambar_ditolak(): void
    {
        // Ekstensinya .jpg tapi isinya skrip. mimetypes memeriksa isi berkasnya,
        // bukan namanya.
        $jalur = sys_get_temp_dir().'/'.uniqid('jahat').'.jpg';
        file_put_contents($jalur, "<?php echo 'halo'; ?>");

        $this->unggah(new UploadedFile($jalur, 'foto.jpg', 'image/jpeg', null, true))
            ->assertSessionHasErrors('foto');

        $this->assertNull($this->panitia->fresh()->photo_path);
    }

    public function test_berkas_terlalu_besar_ditolak(): void
    {
        $this->unggah(UploadedFile::fake()->create('besar.jpg', 4096, 'image/jpeg'))
            ->assertSessionHasErrors('foto');
    }

    /* ------------------------------------------------------- Hak akses */

    public function test_panitia_biasa_tidak_bisa_mengunggah_foto(): void
    {
        // Menerbitkan identitas hanya boleh developer — kalau tidak, siapa pun
        // panitia bisa memasang foto orang lain di kartu.
        $this->unggah(null, null, $this->panitia)->assertForbidden();

        $this->assertNull($this->panitia->fresh()->photo_path);
    }

    public function test_akun_peserta_tidak_bisa_dibuatkan_pas_foto(): void
    {
        $this->unggah(null, $this->peserta)->assertNotFound();
    }

    public function test_foto_hanya_bisa_dibuka_pengelola(): void
    {
        $this->unggah();

        $this->actingAs($this->panitia)
            ->get("/panitia/kartu-panitia/{$this->panitia->id}/foto")
            ->assertOk();

        $this->actingAs($this->peserta)
            ->get("/panitia/kartu-panitia/{$this->panitia->id}/foto")
            ->assertForbidden();
    }

    public function test_tamu_tidak_bisa_membuka_foto(): void
    {
        $this->unggah();

        // Pengunggahan berjalan sebagai developer; tanpa ini permintaan di bawah
        // masih terautentikasi dan yang teruji jadi penjaga peran, bukan auth.
        auth()->logout();

        $this->get("/panitia/kartu-panitia/{$this->panitia->id}/foto")
            ->assertRedirect('/masuk');
    }

    /* --------------------------------------------------- Tampil di kartu */

    public function test_kartu_membawa_tautan_foto_setelah_diunggah(): void
    {
        $this->actingAs($this->developer)
            ->get("/panitia/kartu-panitia/lembar?ids={$this->panitia->id}")
            ->assertInertia(fn ($page) => $page->where('kartu.0.foto', null));

        $this->unggah();

        $this->actingAs($this->developer)
            ->get("/panitia/kartu-panitia/lembar?ids={$this->panitia->id}")
            ->assertInertia(fn ($page) => $page
                ->where('kartu.0.foto', fn ($f) => str_contains($f, "/kartu-panitia/{$this->panitia->id}/foto")));
    }

    public function test_kartu_tetap_bisa_dicetak_tanpa_foto(): void
    {
        // Sebagian panitia tidak sempat menyiapkan fotonya; kartunya tetap
        // harus keluar, dengan bingkai kosong untuk ditempel manual.
        $this->actingAs($this->developer)
            ->get("/panitia/kartu-panitia/lembar?ids={$this->panitia->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('kartu', 1)
                ->where('kartu.0.nama', 'Bendahara IKA')
                ->where('kartu.0.foto', null));
    }
}
