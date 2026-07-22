<?php

namespace App\Http\Controllers\Panitia;

use App\Http\Controllers\Controller;
use App\Models\RaceCategory;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = RaceCategory::orderBy('sort_order')->get()->map(fn (RaceCategory $c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name,
            'distance_label' => $c->distance_label,
            'tagline' => $c->tagline,
            'price' => $c->price,
            'quota' => $c->quota,
            'taken' => $c->takenSlots(),
            'remaining' => $c->remainingSlots(),
            'confirmed' => $c->registrations()->where('status', Registration::STATUS_CONFIRMED)->count(),
            'is_active' => $c->is_active,
            'is_featured' => $c->is_featured,
        ]);

        return Inertia::render('Panitia/Categories', [
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, RaceCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'quota' => ['required', 'integer', 'min:'.$category->takenSlots(), 'max:100000'],
            'is_active' => ['required', 'boolean'],
        ], [
            'quota.min' => 'Kuota tidak boleh lebih kecil dari jumlah slot yang sudah terpakai ('.$category->takenSlots().').',
        ], [
            'name' => 'nama kategori',
            'tagline' => 'tagline',
            'price' => 'biaya pendaftaran',
            'quota' => 'kuota',
        ]);

        $category->update($validated);

        return back()->with('success', "Kategori {$category->distance_label} diperbarui.");
    }
}
