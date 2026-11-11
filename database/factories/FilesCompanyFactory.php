<?php

namespace Database\Factories;

use App\Models\FilesCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FilesCompany>
 */
class FilesCompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'policies',
            'contracts',
            'legal',
            'financial',
            'marketing',
            'hr',
            'it',
            'operations',
        ];

        $category = fake()->randomElement($categories);
        $fileName = fake()->words(2, true);

        $mimes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
        ];
        $extension = fake()->randomElement(array_keys($mimes));

        // document_path holds the Cloudinary public id, as the controller stores it.
        return [
            'document_name' => ucfirst(fake()->words(3, true)),
            'document_path' => "hris/company-files/{$category}/".str_replace(' ', '-', $fileName),
            'type_file' => $mimes[$extension],
            'size_file' => fake()->numberBetween(10_000, 5_000_000),
            'description' => fake()->optional(0.8)->sentence(),
        ];
    }
}

