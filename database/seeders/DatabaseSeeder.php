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
            ConfigurableOptionSeeder::class,

            // Business Documents: reference data for Letters (Surat) numbering
            LetterCodeSeeder::class,
            DivisionCodeSeeder::class,
        ]);
    }
}
