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
        $fileTypes = [
            'pdf' => 'PDF Document',
            'docx' => 'Word Document',
            'xlsx' => 'Excel Spreadsheet',
            'pptx' => 'PowerPoint Presentation',
            'png' => 'Image',
            'jpg' => 'Image',
        ];

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

        $fileType = fake()->randomElement(array_keys($fileTypes));
        $category = fake()->randomElement($categories);
        $fileName = fake()->words(2, true);

        return [
            'path' => "company-files/{$category}/{$fileName}.{$fileType}",
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.8)->sentence(),
        ];
    }
}

