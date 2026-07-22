<?php

namespace Database\Seeders;

use App\Models\RaceCategory;
use Illuminate\Database\Seeder;

class RaceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => '5k',
                'name' => 'Fun Run 5K',
                'distance_label' => '5K',
                'tagline' => 'Jarak santai buat pemula & keluarga. Bisa lari, bisa jalan.',
                'description' => 'Kategori 5K dirancang untuk semua umur. Satu water station sekaligus pos medis tersedia di KM 2,5. Terbuka untuk umum.',
                // Disusun sejajar dengan 10K: [yang khas] · [dukungan di rute] ·
                // [yang dibawa pulang]. Sebelumnya "Boleh lari atau jalan"
                // mengulang tagline persis di atasnya, dan dukungan rutenya
                // tidak disebut sama sekali sehingga 5K terkesan tanpa
                // water station.
                'features' => [
                    'Cut-off longgar 90 menit',
                    'Water station + pos medis di KM 2,5',
                    'Race pack + medali finisher',
                ],
                'price' => 100000,
                'quota' => 2000,
                // Nomor BIB sengaja diawali angka kategorinya: 5K -> 5xxx.
                'bib_start' => 5001,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => '10k',
                'name' => 'Challenge 10K',
                'distance_label' => '10K',
                'tagline' => 'Buat yang mau uji napas dan mengejar catatan waktu terbaik.',
                'description' => 'Kategori 10K menggunakan timing chip dengan kategori juara putra & putri. Empat water station dan tiga pos medis tersebar di sepanjang rute.',
                'features' => [
                    'Timing chip & kategori juara',
                    '4 water station + 3 pos medis',
                    'Race pack + medali finisher',
                ],
                'price' => 150000,
                'quota' => 1000,
                // 10K -> 10xxx, jadi kategorinya langsung terbaca dari nomor BIB.
                'bib_start' => 10001,
                'is_featured' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($categories as $category) {
            RaceCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
