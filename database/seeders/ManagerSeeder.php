<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\EmployeeCodeGenerator;
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

        $employeeProfile = $manager->employeeProfile()->create([
            'code' => app(EmployeeCodeGenerator::class)->generate('full_time', '2025-12-10'),
            'identity_number' => '111121121',
            'phone' => '081234567892',
            'date_of_birth' => '1995-01-01',
            'gender' => 'male',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ]);

        $employeeProfile->jobInformation()->create([
            'employee_id' => $employeeProfile->id,
            'job_title' => 'Manager',
            'years_experience' => 8,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2025-12-10',
            'monthly_salary' => 15000000,
            'skill_level' => 'expert',
        ]);

        $employeeProfile->bankInformation()->create([
            'employee_id' => $employeeProfile->id,
            'bank_name' => 'bca',
            'account_number' => '122333444',
            'account_holder_name' => env('SEED_MANAGER_NAME', 'Rizky Aji Kurniawan'),
            'account_type' => 'savings',
        ]);

        $employeeProfile->emergencyContacts()->create([
            'employee_id' => $employeeProfile->id,
            'full_name' => 'Manager',
            'phone' => '081234567892',
            'relationship' => 'Family',
            'email' => 'manager.emergency@gmail.com',
        ]);

        $manager->assignRole('manager');
    }
}
