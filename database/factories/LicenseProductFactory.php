<?php

namespace Database\Factories;

use App\Models\LicenseProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseProduct>
 */
class LicenseProductFactory extends Factory
{
    public function definition(): array
    {
        $word = fake()->unique()->lexify('app-????');

        return [
            'code' => $word,
            'name' => ucwords(str_replace('-', ' ', $word)),
            'key_prefix' => strtoupper(fake()->unique()->lexify('????')),
            'description' => null,
            'is_active' => true,
        ];
    }

    /**
     * The second product used by the tests.
     */
    public function demo(): static
    {
        return $this->state(fn () => ['code' => 'demo-app', 'name' => 'Demo App', 'key_prefix' => 'DEMO']);
    }
}
