<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manager = User::create([
            'name' => env('SEED_MANAGER_NAME', 'Rizky Aji Kurniawan'),
            'email' => env('SEED_MANAGER_EMAIL', 'manager@example.com'),
            'password' => bcrypt(env('SEED_MANAGER_PASSWORD', 'password')),
            'profile_photo' => 'profile-pictures/male/1.avif',
        ]);

        $manager->assignRole('manager');
    }
}
