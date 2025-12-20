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
            'name' => 'Rizky AK, S.Kom.',
            'email' => 'rizkyjcd@jcdigital.co.id',
            'password' => bcrypt('atasizinAllah#2511'),
            'profile_photo' => 'profile-pictures/male/1.avif',
        ]);

        $manager->assignRole('manager');
    }
}
