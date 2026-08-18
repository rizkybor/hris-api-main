<?php

namespace Database\Seeders;

use App\Models\DivisionCode;
use Illuminate\Database\Seeder;

class DivisionCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'BD', 'name' => 'Business Development'],
            ['code' => 'CR', 'name' => 'Creative/Branding'],
            ['code' => 'DIR', 'name' => 'Direksi'],
            ['code' => 'FIN', 'name' => 'Keuangan'],
            ['code' => 'GA', 'name' => 'General Affairs / Administrasi'],
            ['code' => 'HR', 'name' => 'Human Resources'],
            ['code' => 'IT', 'name' => 'Teknologi Informasi'],
            ['code' => 'LEG', 'name' => 'Legal'],
            ['code' => 'OPS', 'name' => 'Operasional'],
            ['code' => 'PRC', 'name' => 'Procurement'],
        ];

        // Replace the previous set entirely -- keep only what's listed
        // above, since it's the authoritative company division list.
        DivisionCode::whereNotIn('code', array_column($codes, 'code'))->delete();

        foreach ($codes as $code) {
            DivisionCode::updateOrCreate(['code' => $code['code']], $code);
        }
    }
}
