<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Seed roles and permissions first
            RoleSeeder::class,

            // 2. Seed specific users (Manager, HR, Finance, Employee)
            ManagerSeeder::class,
            EmployeeSeeder::class,
            HrSeeder::class,
            FinanceSeeder::class,

            // 3. Seed employee profiles with complete data (User, Profile, Job, Bank, Emergency Contacts)
            // EmployeeProfileSeeder::class,

            // 4. Seed teams and assign employees to teams (requires employees to exist)
            // TeamSeeder::class,
        ]);
    }
}
