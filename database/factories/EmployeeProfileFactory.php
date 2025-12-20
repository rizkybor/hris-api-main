<?php

namespace Database\Factories;

use App\Models\User;
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

        // Create user with matching profile picture
        $profilePicture = $this->getProfilePictureByGender($gender);

        return [
            'user_id' => User::factory()->state([
                'profile_photo' => $profilePicture,
            ])->afterCreating(function (User $user) {
                $user->assignRole('employee');
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
     * Get profile picture based on gender
     */
    private function getProfilePictureByGender(string $gender): string
    {
        $number = fake()->numberBetween(1, 3);

        return "profile-pictures/{$gender}/{$number}.avif";
    }

    /**
     * Generate employee code with format EMP-YYYY-XXXXXX (up to 999999 for 500k employees)
     */
    private function generateEmployeeCode(): string
    {
        static $counter = 1;

        $year = date('Y');
        $number = str_pad($counter, 6, '0', STR_PAD_LEFT);
        $counter++;

        return "EMP-{$year}-{$number}";
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
        $profilePicture = $this->getProfilePictureByGender('male');

        return $this->state(fn(array $attributes) => [
            'gender' => 'male',
            'user_id' => User::factory()->state([
                'profile_photo' => $profilePicture,
            ])->afterCreating(function (User $user) {
                $user->assignRole('employee');
            }),
        ]);
    }

    /**
     * Indicate that the employee is female.
     */
    public function female(): static
    {
        $profilePicture = $this->getProfilePictureByGender('female');

        return $this->state(fn(array $attributes) => [
            'gender' => 'female',
            'user_id' => User::factory()->state([
                'profile_photo' => $profilePicture,
            ])->afterCreating(function (User $user) {
                $user->assignRole('employee');
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
