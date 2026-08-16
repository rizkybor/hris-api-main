<?php

namespace Database\Seeders;

use App\Models\LetterCode;
use Illuminate\Database\Seeder;

class LetterCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'SP', 'name' => 'Surat Pemberitahuan'],
            ['code' => 'SK', 'name' => 'Surat Keputusan'],
            ['code' => 'SPK', 'name' => 'Surat Perintah Kerja'],
            ['code' => 'SKET', 'name' => 'Surat Keterangan'],
            ['code' => 'ST', 'name' => 'Surat Tugas'],
            ['code' => 'SK-KTS', 'name' => 'Surat Kontrak Kerjasama'],
            ['code' => 'BAST', 'name' => 'Berita Acara Serah Terima'],
            ['code' => 'NDA', 'name' => 'Non-Disclosure Agreement'],
            ['code' => 'PEN', 'name' => 'Surat Penawaran'],
            ['code' => 'SP1', 'name' => 'Surat Peringatan Pertama'],
            ['code' => 'SP2', 'name' => 'Surat Peringatan Kedua'],
            ['code' => 'SP3', 'name' => 'Surat Peringatan Ketiga (Terakhir)'],
        ];

        foreach ($codes as $code) {
            LetterCode::firstOrCreate(['code' => $code['code']], $code);
        }
    }
}
