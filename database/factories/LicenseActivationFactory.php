<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseActivation>
 */
class LicenseActivationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'device_id' => fake()->uuid(),
            'device_name' => fake()->randomElement(['MacBook Pro', 'iPad Pro', 'Mac mini', 'iPad Air']),
            'platform' => fake()->randomElement(['macOS', 'iPadOS']),
            'app_version' => '1.0.0',
            'activated_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
