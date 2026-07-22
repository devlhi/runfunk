<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'developer@gongfunrun.id'],
            [
                'name' => 'Developer',
                'password' => 'developer123',
                'role' => User::ROLE_DEVELOPER,
                'phone' => '081200000001',
                'city' => 'Kabupaten Gorontalo',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'panitia@gongfunrun.id'],
            [
                'name' => 'Admin Panitia',
                'password' => 'panitia123',
                'role' => User::ROLE_PANITIA,
                'phone' => '081200000000',
                'city' => 'Kabupaten Gorontalo',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'peserta@example.com'],
            [
                'name' => 'Rian Pelari',
                'password' => 'peserta123',
                'role' => User::ROLE_PESERTA,
                'phone' => '081311112222',
                'gender' => 'L',
                'birth_date' => '1998-04-12',
                'city' => 'Gorontalo',
                'email_verified_at' => now(),
            ]
        );
    }
}
