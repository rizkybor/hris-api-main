<?php

namespace Database\Seeders;

use App\Models\SdmField;
use Illuminate\Database\Seeder;

class SdmFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            'UI/UX Designer',
            'Frontend Developer',
            'Backend Developer',
            'Fullstack Developer',
            'Mobile Developer',
            'QA Engineer',
            'DevOps Engineer',
            'Project Manager',
            'Business Analyst',
            'Product Owner',
        ];

        foreach ($fields as $name) {
            SdmField::firstOrCreate(['name' => $name]);
        }
    }
}
