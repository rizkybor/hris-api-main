<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employee = User::create([
            'name' => 'Hr',
            'email' => 'hr@gmail.com',
            'password' => bcrypt('password'),
            'profile_photo' => 'profile-pictures/male/3.avif',
        ]);

        $employeeProfile = $employee->employeeProfile()->create([
            'code' => 'HR001',
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
            'job_title' => 'Hr',
            'years_experience' => 5,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
            'skill_level' => 'expert',
        ]);

        $employeeProfile->bankInformation()->create([
            'employee_id' => $employeeProfile->id,
            'bank_name' => 'bca',
            'account_number' => '244422131',
            'account_holder_name' => 'Hr',
            'account_type' => 'saving',
        ]);

        $employeeProfile->emergencyContacts()->create([
            'employee_id' => $employeeProfile->id,
            'full_name' => 'Hr',
            'phone' => '081234567890',
            'relationship' => 'Family',
            'email' => 'hr@gmail.com',
        ]);

        $employee->assignRole('hr');
    }
}
