<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $sponsors = [
            ['name' => 'Pemdes Tuladenggi', 'tier' => Sponsor::TIER_UTAMA, 'sort_order' => 1],
            ['name' => 'Karang Taruna', 'tier' => Sponsor::TIER_UTAMA, 'sort_order' => 2],
            ['name' => 'Sponsor Pendukung', 'tier' => Sponsor::TIER_PENDUKUNG, 'sort_order' => 1],
            ['name' => 'Media Partner', 'tier' => Sponsor::TIER_MEDIA, 'sort_order' => 1],
        ];

        foreach ($sponsors as $sponsor) {
            Sponsor::updateOrCreate(['name' => $sponsor['name']], $sponsor + ['is_active' => true]);
        }
    }
}
