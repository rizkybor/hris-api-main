<?php

namespace Database\Factories;

use App\Models\CredentialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CredentialAccount>
 */
class CredentialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $labels = [
            'GitHub Account',
            'AWS Console',
            'Database Admin',
            'Email Service',
            'Project Management Tool',
            'Cloud Storage',
            'Payment Gateway',
            'Social Media Manager',
            'CRM System',
            'Analytics Dashboard',
            'VPN Access',
            'Server SSH',
            'Domain Registrar',
            'CDN Service',
            'Monitoring Tool',
        ];

        $websites = [
            'https://github.com',
            'https://console.aws.amazon.com',
            'https://db.company.com',
            'https://mail.company.com',
            'https://pm.company.com',
            'https://storage.company.com',
            'https://payment.company.com',
            'https://social.company.com',
            'https://crm.company.com',
            'https://analytics.company.com',
        ];

        return [
            'label_password' => fake()->randomElement($labels),
            'username_email' => fake()->unique()->safeEmail(),
            'password' => fake()->password(12, 20) . fake()->randomElement(['!', '@', '#', '$', '%']),
            'website' => fake()->randomElement($websites),
            'notes' => fake()->optional(0.7)->sentence(),
        ];
    }
}

