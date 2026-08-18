<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SponsorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panitia/Sponsors', [
            'sponsors' => Sponsor::displayOrder()->paginate(20)->through(fn (Sponsor $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'tier' => $s->tier,
                'tier_label' => $s->tierLabel(),
                'website_url' => $s->website_url,
                'note' => $s->note,
                'is_active' => $s->is_active,
                'sort_order' => $s->sort_order,
                'display_type' => $s->display_type,
                'logo_url' => $s->logoUrl(),
            ]),
            'tiers' => collect(Sponsor::tiers())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            // Dihitung di basis data, bukan dari baris yang terkirim: setelah
            // dipaginasi, menghitung di sisi Vue hanya mengenai halaman terbuka.
            'aktifCount' => Sponsor::where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('sponsors', 'public');
        }

        Sponsor::create($data);

        return back()->with('success', 'Sponsor ditambahkan dan langsung tampil di landing page.');
    }

    public function update(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $data = $this->validated($request, $sponsor);

        if ($request->hasFile('logo')) {
            $sponsor->deleteLogoFile();
            $data['logo_path'] = $request->file('logo')->store('sponsors', 'public');
        } elseif ($request->boolean('remove_logo') && $sponsor->logo_path) {
            $sponsor->deleteLogoFile();
            $data['logo_path'] = null;
        }

        $sponsor->update($data);

        return back()->with('success', "Data {$sponsor->name} diperbarui.");
    }

    public function destroy(Sponsor $sponsor): RedirectResponse
    {
        $nama = $sponsor->name;
        $sponsor->deleteLogoFile();
        $sponsor->delete();

        return back()->with('success', "{$nama} dihapus dari daftar sponsor.");
    }

    private function validated(Request $request, ?Sponsor $sponsor = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tier' => ['required', Rule::in(array_keys(Sponsor::tiers()))],
            'website_url' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'display_type' => ['required', Rule::in([Sponsor::DISPLAY_LOGO, Sponsor::DISPLAY_TEKS])],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'nama sponsor',
            'tier' => 'tingkat sponsor',
            'website_url' => 'alamat situs',
            'sort_order' => 'urutan',
            'display_type' => 'mode tampilan',
            'logo' => 'logo sponsor',
        ]);

        // Mode logo hanya boleh kalau ada logo yang akan dipakai:
        // baru diunggah, atau logo lama yang tidak dihapus.
        $akanPunyaLogo = $request->hasFile('logo')
            || ($sponsor?->logo_path !== null && ! $request->boolean('remove_logo'));

        if ($data['display_type'] === Sponsor::DISPLAY_LOGO && ! $akanPunyaLogo) {
            throw ValidationException::withMessages([
                'logo' => 'Unggah logo dulu, atau pilih mode tampilan teks.',
            ]);
        }

        unset($data['logo'], $data['remove_logo']);

        return $data;
    }
}
