<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            // OCCA unless a product is chosen with ->product('demo-app').
            'product_id' => fn () => LicenseProduct::query()->where('code', LicenseProduct::OCCA)->value('id'),
            'sequence' => $sequence = fake()->unique()->numberBetween(9000, 99999),
            'key' => License::generateKey('OC', License::PLAN_PROFESSIONAL, $sequence),
            'licensee_name' => fake()->name(),
            'licensee_email' => fake()->unique()->safeEmail(),
            'phone' => '+62 812-3456-7890',
            'country' => 'Indonesia',
            'province' => 'Daerah Khusus Ibukota Jakarta',
            'regency' => 'Kota Administrasi Jakarta Pusat',
            'district' => 'Gambir',
            'village' => 'Gambir',
            'postal_code' => '10110',
            'province_code' => '31',
            'regency_code' => '31.71',
            'district_code' => '31.71.01',
            'village_code' => '31.71.01.1001',
            'occupation' => 'Mapper',
            'plan' => License::PLAN_PROFESSIONAL,
            'type' => License::TYPE_TERM,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'max_devices' => 2,
            'is_active' => true,
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }

    /**
     * Lifetime type, which only the Lifetime plan has.
     */
    public function lifetime(): static
    {
        return $this->state(fn () => [
            'plan' => License::PLAN_LIFETIME,
            'type' => License::TYPE_LIFETIME,
            'expires_at' => null,
        ]);
    }

    /**
     * The lifetime plan also makes the license type lifetime; organization
     * gets the organization fields instead of the individual ones.
     */
    public function plan(string $plan): static
    {
        return $this->state(function () use ($plan) {
            $state = ['plan' => $plan];

            if ($plan === License::PLAN_LIFETIME) {
                $state += ['type' => License::TYPE_LIFETIME, 'expires_at' => null];
            }

            if ($plan === License::PLAN_ORGANIZATION) {
                $state += [
                    'occupation' => null,
                    'affiliation' => null,
                    'organization_type' => 'club',
                    'contact_name' => fake()->name(),
                    'address' => 'Jl. Medan Merdeka Barat 12',
                ];
            }

            return $state;
        });
    }

    /**
     * A license of the product with this code (key prefix included).
     */
    public function product(string $code): static
    {
        return $this->state(function () use ($code) {
            $product = LicenseProduct::findByCodeOrFail($code);

            $sequence = fake()->unique()->numberBetween(9000, 99999);

            return ['product_id' => $product->id, 'sequence' => $sequence, 'key' => License::generateKey($product->key_prefix, License::PLAN_PROFESSIONAL, $sequence)];
        });
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'type' => License::TYPE_TERM,
            'starts_at' => now()->subYear()->subDay(),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
