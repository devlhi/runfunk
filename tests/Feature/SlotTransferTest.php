<?php

namespace Tests\Feature;

use App\Models\RaceCategory;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RaceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $peserta;

    private User $pesertaLain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RaceCategorySeeder::class);

        $this->peserta = User::create([
            'name' => 'Peserta', 'email' => 'peserta@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);
        $this->pesertaLain = User::create([
            'name' => 'Peserta Lain', 'email' => 'lain@example.com',
            'password' => 'rahasia12345', 'role' => User::ROLE_PESERTA, 'email_verified_at' => now(),
        ]);

        // Acara masih jauh, jadi pengalihan seharusnya terbuka.
        Setting::simpan(['event_date' => now()->addMonths(3)->toIso8601String()]);
    }

    private function buatSlot(array $override = []): Registration
    {
        return Registration::create([
            'registration_code' => 'GFR-5K-0001',
            'user_id' => $this->peserta->id,
            'race_category_id' => RaceCategory::where('slug', '5k')->first()->id,
            'participant_name' => 'Rian Pelari',
            'participant_email' => 'rian@example.com',
            'participant_phone' => '081234567890',
            'gender' => 'L', 'birth_date' => '1996-08-17', 'city' => 'Gorontalo',
            'jersey_size' => 'M', 'emergency_name' => 'Ibu', 'emergency_phone' => '0813',
            'amount' => 100000,
            'status' => $override['status'] ?? Registration::STATUS_CONFIRMED,
            'bib_number' => $override['bib'] ?? '5001',
        ] + $override);
    }

    private function dataPengganti(array $override = []): array
    {
        return [
            'participant_name' => 'Sitti Rahma',
            'participant_email' => 'sitti@example.com',
            'participant_phone' => '081399998888',
            'gender' => 'P',
            'birth_date' => '1999-05-20',
            'city' => 'Limboto',
            'jersey_size' => 'L',
            'emergency_name' => 'Bapak Rahma',
            'emergency_phone' => '081377776666',
            'konfirmasi' => true,
            ...$override,
        ];
    }

    public function test_peserta_bisa_mengalihkan_slotnya(): void
    {
        $slot = $this->buatSlot();

        $this->actingAs($this->peserta)
            ->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti())
            ->assertRedirect();

        $slot->refresh();

        $this->assertSame('Sitti Rahma', $slot->participant_name);
        $this->assertSame('L', $slot->jersey_size);
        $this->assertSame('Rian Pelari', $slot->transferred_from);
        $this->assertNotNull($slot->transferred_at);
    }

    public function test_nomor_bib_dan_kode_tidak_berubah_setelah_dialihkan(): void
    {
        $slot = $this->buatSlot();

        $this->actingAs($this->peserta)->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti());

        $slot->refresh();

        // Slot yang sama berpindah tangan — bukan pendaftaran baru.
        $this->assertSame('5001', $slot->bib_number);
        $this->assertSame('GFR-5K-0001', $slot->registration_code);
        $this->assertSame($this->peserta->id, $slot->user_id);
        $this->assertSame(Registration::STATUS_CONFIRMED, $slot->status);
    }

    public function test_slot_orang_lain_tidak_bisa_dialihkan(): void
    {
        $slot = $this->buatSlot();

        $this->actingAs($this->pesertaLain)
            ->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti())
            ->assertForbidden();

        $this->assertSame('Rian Pelari', $slot->fresh()->participant_name);
    }

    public function test_pendaftaran_belum_lunas_tidak_bisa_dialihkan(): void
    {
        $slot = $this->buatSlot(['status' => Registration::STATUS_PENDING_PAYMENT, 'bib' => null]);

        $this->actingAs($this->peserta)
            ->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti())
            ->assertSessionHasErrors('participant_name');

        $this->assertSame('Rian Pelari', $slot->fresh()->participant_name);
    }

    public function test_slot_hanya_bisa_dialihkan_sekali(): void
    {
        $slot = $this->buatSlot();

        $this->actingAs($this->peserta)->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti());

        // Percobaan kedua harus ditolak.
        $this->actingAs($this->peserta)
            ->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti([
                'participant_name' => 'Orang Ketiga',
            ]))
            ->assertSessionHasErrors('participant_name');

        $this->assertSame('Sitti Rahma', $slot->fresh()->participant_name);
    }

    public function test_pengalihan_tertutup_setelah_h7(): void
    {
        // Acara tinggal 3 hari lagi — sudah lewat batas H-7.
        Setting::simpan(['event_date' => now()->addDays(3)->toIso8601String()]);
        $slot = $this->buatSlot();

        $this->actingAs($this->peserta)
            ->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti())
            ->assertSessionHasErrors('participant_name');

        $this->assertNull($slot->fresh()->transferred_at);
    }

    public function test_tanpa_mencentang_konfirmasi_ditolak(): void
    {
        $slot = $this->buatSlot();

        $this->actingAs($this->peserta)
            ->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti(['konfirmasi' => false]))
            ->assertSessionHasErrors('konfirmasi');

        $this->assertNull($slot->fresh()->transferred_at);
    }

    public function test_halaman_detail_menyembunyikan_tombol_setelah_dialihkan(): void
    {
        $slot = $this->buatSlot();

        $this->actingAs($this->peserta)
            ->get("/pendaftaran/{$slot->id}")
            ->assertInertia(fn ($p) => $p->where('registration.can_transfer', true));

        $this->actingAs($this->peserta)->post("/pendaftaran/{$slot->id}/alihkan", $this->dataPengganti());

        $this->actingAs($this->peserta)
            ->get("/pendaftaran/{$slot->id}")
            ->assertInertia(fn ($p) => $p
                ->where('registration.can_transfer', false)
                ->where('registration.transferred_from', 'Rian Pelari'));
    }
}
