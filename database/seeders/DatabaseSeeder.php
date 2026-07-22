<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RaceCategorySeeder::class,
            SponsorSeeder::class,
            UserSeeder::class,
        ]);
    }
}
