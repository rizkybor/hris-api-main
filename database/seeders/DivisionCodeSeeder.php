<?php

namespace Database\Seeders;

use App\Models\DivisionCode;
use Illuminate\Database\Seeder;

class DivisionCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'KPUS', 'name' => 'Kantor Pusat'],
            ['code' => 'OPS', 'name' => 'Operasional'],
            ['code' => 'HR', 'name' => 'Human Resources'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'BD', 'name' => 'Business Development'],
            ['code' => 'IT', 'name' => 'Information Technology'],
            ['code' => 'GA', 'name' => 'General Affairs'],
        ];

        foreach ($codes as $code) {
            DivisionCode::firstOrCreate(['code' => $code['code']], $code);
        }
    }
}
