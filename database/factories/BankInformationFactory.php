<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankInformation>
 */
class BankInformationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banks = [
            'bca',
            'mandiri',
            'bri',
            'bni',
            'cimb_niaga',
            'danamon',
            'permata',
            'maybank_indonesia',
            'ocbc_nisp',
            'panin_bank',
            'btn',
            'mega',
        ];

        $accountTypes = ['savings', 'checking', 'current'];

        return [
            'employee_id' => EmployeeProfile::factory(),
            'bank_name' => fake()->randomElement($banks),
            'account_number' => $this->generateAccountNumber(),
            'account_holder_name' => fake()->name(),
            'bank_branch' => fake()->city() . ' - ' . fake()->streetName(),
            'account_type' => fake()->randomElement($accountTypes),
        ];
    }

    /**
     * Generate realistic bank account number
     */
    private function generateAccountNumber(): string
    {
        return fake()->numerify('##########');
    }

    /**
     * Indicate that this is a savings account.
     */
    public function savings(): static
    {
        return $this->state(fn(array $attributes) => [
            'account_type' => 'savings',
        ]);
    }

    /**
     * Indicate that this is a payroll account.
     */
    public function payroll(): static
    {
        return $this->state(fn(array $attributes) => [
            'account_type' => 'payroll',
        ]);
    }

    /**
     * Set specific bank
     */
    public function bank(string $bankKey): static
    {
        $banks = [
            'bca' => 'Bank Central Asia (BCA)',
            'mandiri' => 'Bank Mandiri',
            'bri' => 'Bank Rakyat Indonesia (BRI)',
            'bni' => 'Bank Negara Indonesia (BNI)',
            'cimb_niaga' => 'Bank CIMB Niaga',
            'danamon' => 'Bank Danamon',
            'permata' => 'Bank Permata',
            'maybank_indonesia' => 'Bank Maybank',
            'ocbc_nisp' => 'Bank OCBC NISP',
            'panin_bank' => 'Bank Panin',
            'btn' => 'Bank BTN',
            'mega' => 'Bank Mega',
        ];

        return $this->state(fn(array $attributes) => [
            'bank_name' => $banks[$bankKey] ?? $bankKey,
        ]);
    }

    /**
     * Assign to specific employee
     */
    public function forEmployee(EmployeeProfile $employee): static
    {
        return $this->state(fn(array $attributes) => [
            'employee_id' => $employee->id,
            'account_holder_name' => $employee->user->name ?? fake()->name(),
        ]);
    }
}
