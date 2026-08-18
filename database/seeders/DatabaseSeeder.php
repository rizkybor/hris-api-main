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

            // 2. Seed specific users (Super Admin, Manager, HR, Finance)
            SuperAdminSeeder::class,
            ManagerSeeder::class,
            OperationalDirectorSeeder::class,
            FinanceSeeder::class,
            CredentialAccountSeeder::class,
            FilesCompanySeeder::class,
            SdmFieldSeeder::class,

            // Business Documents: reference data for Letters (Surat) numbering
            LetterCodeSeeder::class,
            DivisionCodeSeeder::class,

            // 3. Seed employee profiles with complete data (User, Profile, Job, Bank, Emergency Contacts)
            // EmployeeProfileSeeder::class,

            // 4. Seed teams and assign employees to teams (requires employees to exist)
            // TeamSeeder::class,
        ]);
    }
}
