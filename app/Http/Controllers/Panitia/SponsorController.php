<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        Sponsor::create($this->validated($request));

        return back()->with('success', 'Sponsor ditambahkan dan langsung tampil di landing page.');
    }

    public function update(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $sponsor->update($this->validated($request));

        return back()->with('success', "Data {$sponsor->name} diperbarui.");
    }

    public function destroy(Sponsor $sponsor): RedirectResponse
    {
        $nama = $sponsor->name;
        $sponsor->delete();

        return back()->with('success', "{$nama} dihapus dari daftar sponsor.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tier' => ['required', Rule::in(array_keys(Sponsor::tiers()))],
            'website_url' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], [
            'name' => 'nama sponsor',
            'tier' => 'tingkat sponsor',
            'website_url' => 'alamat situs',
            'sort_order' => 'urutan',
        ]);
    }
}
