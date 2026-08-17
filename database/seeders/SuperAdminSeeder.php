<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * The one account that always exists, has every permission, and can
     * never be deleted (see User::PROTECTED_EMAIL). It has no employee
     * profile -- it's a system-level account, not a staff member.
     *
     * No profile_photo is seeded on purpose -- the Avatar component falls
     * back to colored initials, so there's no need for a stock photo.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => User::PROTECTED_EMAIL],
            [
                'name' => env('SEED_SUPERADMIN_NAME', 'Super Admin'),
                'password' => bcrypt(env('SEED_SUPERADMIN_PASSWORD', 'password')),
            ]
        );

        if (! $user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
        }
    }
}
