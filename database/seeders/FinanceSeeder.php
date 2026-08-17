<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\EmployeeCodeGenerator;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employee = User::create([
            'name' => env('SEED_FINANCE_NAME', 'Andy Saputra'),
            'email' => env('SEED_FINANCE_EMAIL', 'finance@example.com'),
            'password' => bcrypt(env('SEED_FINANCE_PASSWORD', 'password')),
            'profile_photo' => 'profile-pictures/female/1.avif',
        ]);

        $employeeProfile = $employee->employeeProfile()->create([
            'code' => app(EmployeeCodeGenerator::class)->generate('full_time', '2025-12-10'),
            'identity_number' => '333434141',
            'phone' => '081234567891',
            'date_of_birth' => '1995-05-15',
            'gender' => 'female',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Thamrin No. 5',
            'city' => 'Jakarta',
            'postal_code' => '10350',
        ]);

        $employeeProfile->jobInformation()->create([
            'employee_id' => $employeeProfile->id,
            'job_title' => 'Finance Manager',
            'years_experience' => 7,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2025-12-10',
            'monthly_salary' => 12000000,
            'skill_level' => 'expert',
        ]);

        $employeeProfile->bankInformation()->create([
            'employee_id' => $employeeProfile->id,
            'bank_name' => 'bca',
            'account_number' => '9876543210',
            'account_holder_name' => 'Andy Saputra',
            'account_type' => 'savings',
        ]);

        $employeeProfile->emergencyContacts()->create([
            'employee_id' => $employeeProfile->id,
            'full_name' => 'Finance Emergency Contact',
            'phone' => '081234567891',
            'relationship' => 'Family',
            'email' => 'finance.emergency@gmail.com',
        ]);

        $employee->assignRole('finance');
    }
}
