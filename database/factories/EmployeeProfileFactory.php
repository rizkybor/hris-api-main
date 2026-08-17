<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\EmployeeCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        $dateOfBirth = fake()->dateTimeBetween('-55 years', '-22 years');

        return [
            // No profile_photo -- Avatar falls back to colored initials.
            'user_id' => User::factory()->afterCreating(function (User $user) {
                $user->assignRole('staff');
            }),
            'code' => $this->generateEmployeeCode(),
            'identity_number' => $this->generateIdentityNumber(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'hobby' => $this->generateHobby(),
            'place_of_birth' => fake()->city(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'preferred_language' => fake()->randomElement(['English', 'Indonesian', 'Mandarin', 'Japanese']),
            'additional_notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Employee code in the live format ([COUNTRY]JCD[YEAR]-[COMPANY ID][TYPE][5-DIGIT ID]-[CHECK DIGIT]).
     * The employment type isn't known yet at this point (it's set on the
     * related JobInformation record afterwards), so this always issues a
     * Full-Time-series code; it's fake data, not something re-issued later.
     */
    private function generateEmployeeCode(): string
    {
        return app(EmployeeCodeGenerator::class)->generate('full_time');
    }

    /**
     * Generate realistic identity number (NIK format for Indonesia)
     */
    private function generateIdentityNumber(): string
    {
        return fake()->numerify('##############');
    }

    /**
     * Generate realistic hobbies
     */
    private function generateHobby(): string
    {
        $hobbies = [
            'Reading',
            'Photography',
            'Traveling',
            'Gaming',
            'Cooking',
            'Music',
            'Sports',
            'Hiking',
            'Painting',
            'Writing',
            'Cycling',
            'Swimming',
            'Running',
            'Yoga',
            'Dancing',
            'Gardening',
            'Programming',
            'Watching Movies',
            'Playing Guitar',
            'Basketball',
            'Football',
            'Badminton',
            'Chess',
            'Board Games',
        ];

        return implode(', ', fake()->randomElements($hobbies, fake()->numberBetween(1, 3)));
    }

    /**
     * Indicate that the employee is male.
     */
    public function male(): static
    {
        return $this->state(fn(array $attributes) => [
            'gender' => 'male',
            'user_id' => User::factory()->afterCreating(function (User $user) {
                $user->assignRole('staff');
            }),
        ]);
    }

    /**
     * Indicate that the employee is female.
     */
    public function female(): static
    {
        return $this->state(fn(array $attributes) => [
            'gender' => 'female',
            'user_id' => User::factory()->afterCreating(function (User $user) {
                $user->assignRole('staff');
            }),
        ]);
    }

    /**
     * Indicate that the employee is senior (older age).
     */
    public function senior(): static
    {
        return $this->state(fn(array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-40 years'),
        ]);
    }

    /**
     * Indicate that the employee is junior (younger age).
     */
    public function junior(): static
    {
        return $this->state(fn(array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-30 years', '-22 years'),
        ]);
    }

    /**
     * Create employee with specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
