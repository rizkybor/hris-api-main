<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\EmployeeCodeGenerator;
use Illuminate\Database\Seeder;

class OperationalDirectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * No profile_photo is seeded on purpose -- the Avatar component falls
     * back to colored initials, so there's no need for a stock photo.
     */
    public function run(): void
    {
        $employee = User::create([
            'name' => env('SEED_HR_NAME', 'Aldi Pratama Putra'),
            'email' => env('SEED_HR_EMAIL', 'hr@example.com'),
            'password' => bcrypt(env('SEED_HR_PASSWORD', 'password')),
        ]);

        $employeeProfile = $employee->employeeProfile()->create([
            'code' => app(EmployeeCodeGenerator::class)->generate('full_time', '2025-12-10'),
            'identity_number' => '222323131',
            'phone' => '081234567890',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ]);

        $employeeProfile->jobInformation()->create([
            'employee_id' => $employeeProfile->id,
            'job_title' => 'Operational Director',
            'years_experience' => 5,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => '2025-12-10',
            'monthly_salary' => 10000000,
            'skill_level' => 'expert',
        ]);

        $employeeProfile->bankInformation()->create([
            'employee_id' => $employeeProfile->id,
            'bank_name' => 'bca',
            'account_number' => '244422131',
            'account_holder_name' => 'Aldi Pratama Putra',
            'account_type' => 'savings',
        ]);

        $employeeProfile->emergencyContacts()->create([
            'employee_id' => $employeeProfile->id,
            'full_name' => 'Aldi Emergency Contact',
            'phone' => '081234567890',
            'relationship' => 'Family',
            'email' => 'hr@gmail.com',
        ]);

        $employee->assignRole('operational_director');
    }
}
