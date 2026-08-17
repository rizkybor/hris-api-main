<?php

namespace Database\Seeders;

use App\Models\ConfigurableOption;
use Illuminate\Database\Seeder;

class ConfigurableOptionSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'employment_type' => [
                ['value' => 'full_time', 'label' => 'Full-time'],
                ['value' => 'part_time', 'label' => 'Part-time'],
                ['value' => 'contract', 'label' => 'Contract'],
                ['value' => 'intern', 'label' => 'Intern'],
            ],
            'ptkp_status' => [
                ['value' => 'TK/0', 'label' => 'TK/0 - Tidak Kawin, 0 Tanggungan'],
                ['value' => 'TK/1', 'label' => 'TK/1 - Tidak Kawin, 1 Tanggungan'],
                ['value' => 'TK/2', 'label' => 'TK/2 - Tidak Kawin, 2 Tanggungan'],
                ['value' => 'TK/3', 'label' => 'TK/3 - Tidak Kawin, 3 Tanggungan'],
                ['value' => 'K/0', 'label' => 'K/0 - Kawin, 0 Tanggungan'],
                ['value' => 'K/1', 'label' => 'K/1 - Kawin, 1 Tanggungan'],
                ['value' => 'K/2', 'label' => 'K/2 - Kawin, 2 Tanggungan'],
                ['value' => 'K/3', 'label' => 'K/3 - Kawin, 3 Tanggungan'],
            ],
            'bank_name' => [
                ['value' => 'bca', 'label' => 'Bank Central Asia (BCA)'],
                ['value' => 'mandiri', 'label' => 'Bank Mandiri'],
                ['value' => 'bni', 'label' => 'Bank Negara Indonesia (BNI)'],
                ['value' => 'bri', 'label' => 'Bank Rakyat Indonesia (BRI)'],
                ['value' => 'cimb_niaga', 'label' => 'CIMB Niaga'],
                ['value' => 'danamon', 'label' => 'Bank Danamon'],
                ['value' => 'permata', 'label' => 'Bank Permata'],
                ['value' => 'maybank_indonesia', 'label' => 'Maybank Indonesia'],
                ['value' => 'ocbc_nisp', 'label' => 'OCBC NISP'],
                ['value' => 'panin_bank', 'label' => 'Panin Bank'],
            ],
            'preferred_language' => [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'id', 'label' => 'Indonesian'],
                ['value' => 'es', 'label' => 'Spanish'],
                ['value' => 'fr', 'label' => 'French'],
            ],
        ];

        foreach ($categories as $category => $options) {
            foreach ($options as $index => $option) {
                ConfigurableOption::firstOrCreate(
                    ['category' => $category, 'value' => $option['value']],
                    ['label' => $option['label'], 'sort_order' => $index, 'is_active' => true]
                );
            }
        }
    }
}
