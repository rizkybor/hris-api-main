<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'sanctum',
        ]);

        Role::firstOrCreate([
            'name' => 'hr',
            'guard_name' => 'sanctum',
        ]);

        Role::firstOrCreate([
            'name' => 'finance',
            'guard_name' => 'sanctum',
        ]);

        Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'sanctum',
        ]);
    }
}
