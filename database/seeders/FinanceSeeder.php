<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employee = User::create([
            'name' => 'Finance',
            'email' => 'finance@gmail.com',
            'password' => bcrypt('password'),
            'profile_photo' => 'profile-pictures/female/1.avif',
        ]);

        $employeeProfile = $employee->employeeProfile()->create([
            'code' => 'FIN001',
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
            'start_date' => '2024-01-01',
            'monthly_salary' => 12000000,
            'skill_level' => 'expert',
        ]);

        $employeeProfile->bankInformation()->create([
            'employee_id' => $employeeProfile->id,
            'bank_name' => 'BCA',
            'account_number' => '9876543210',
            'account_holder_name' => 'Finance',
            'account_type' => 'saving',
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
