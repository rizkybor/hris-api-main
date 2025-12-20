<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $relationships = [
            'Spouse',
            'Parent',
            'Mother',
            'Father',
            'Sibling',
            'Brother',
            'Sister',
            'Child',
            'Friend',
            'Partner',
            'Relative',
        ];

        return [
            'employee_id' => EmployeeProfile::factory(),
            'full_name' => fake()->name(),
            'relationship' => fake()->randomElement($relationships),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional(0.7)->email(),
        ];
    }

    /**
     * Indicate that the contact is a spouse.
     */
    public function spouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'Spouse',
        ]);
    }

    /**
     * Indicate that the contact is a parent.
     */
    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => fake()->randomElement(['Mother', 'Father']),
        ]);
    }

    /**
     * Indicate that the contact is a sibling.
     */
    public function sibling(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => fake()->randomElement(['Brother', 'Sister']),
        ]);
    }

    /**
     * Assign to specific employee
     */
    public function forEmployee(EmployeeProfile $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
