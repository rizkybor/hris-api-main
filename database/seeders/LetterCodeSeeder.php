<?php

namespace Database\Seeders;

use App\Models\LetterCode;
use Illuminate\Database\Seeder;

class LetterCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'ADN', 'name' => 'Addendum'],
            ['code' => 'AGR', 'name' => 'Agreement / Contract'],
            ['code' => 'SPJ', 'name' => 'Surat Perjanjian'],
            ['code' => 'MOU', 'name' => 'Memorandum of Understanding'],
            ['code' => 'LOI', 'name' => 'Letter of Intent'],
            ['code' => 'SKA', 'name' => 'Surat Kuasa'],
            ['code' => 'SKL', 'name' => 'Surat Klarifikasi'],
            ['code' => 'SKF', 'name' => 'Surat Konfirmasi'],
            ['code' => 'SPK', 'name' => 'Surat Perintah Kerja'],
            ['code' => 'SR', 'name' => 'Surat Rekomendasi'],
            ['code' => 'SP', 'name' => 'Surat Pernyataan'],
            ['code' => 'SW', 'name' => 'Surat Penawaran'],
            ['code' => 'SPH', 'name' => 'Surat Penawaran Harga'],
            ['code' => 'SPP', 'name' => 'Surat Permintaan Penawaran'],
            ['code' => 'SPN', 'name' => 'Surat Penunjukan'],
            ['code' => 'SO', 'name' => 'Sales Order'],
            ['code' => 'NPP', 'name' => 'Nota Permintaan Pembelian'],
            ['code' => 'SK', 'name' => 'Surat Keputusan'],
            ['code' => 'SKI', 'name' => 'Surat Keputusan Internal'],
            ['code' => 'SI', 'name' => 'Standing Instruction'],
            ['code' => 'ST', 'name' => 'Surat Tugas'],
            ['code' => 'MOM', 'name' => 'Minute of Meeting'],
            ['code' => 'BA', 'name' => 'Berita Acara'],
            ['code' => 'BAST', 'name' => 'Berita Acara Serah Terima'],
            ['code' => 'BAPP', 'name' => 'Berita Acara Pemeriksaan Pekerjaan'],
            ['code' => 'BAPL', 'name' => 'Berita Acara Penyelesaian Pekerjaan'],
            ['code' => 'BAPB', 'name' => 'Berita Acara Pembayaran'],
            ['code' => 'SM', 'name' => 'Surat Permohonan'],
            // "SPH" was reused for both Surat Penawaran Harga and Surat
            // Pemberitahuan in the source list -- Surat Pemberitahuan gets
            // its own code (SPB) since letter codes must be unique.
            ['code' => 'SPB', 'name' => 'Surat Pemberitahuan'],
            ['code' => 'SU', 'name' => 'Surat Undangan'],
            ['code' => 'SUE', 'name' => 'Surat Umum Eksternal'],
            ['code' => 'PI', 'name' => 'Pengumuman Internal'],
            ['code' => 'SEI', 'name' => 'Surat Edaran Internal'],
            ['code' => 'LPR', 'name' => 'Laporan Resmi'],
            ['code' => 'LPJ', 'name' => 'Laporan Pertanggungjawaban'],
            ['code' => 'RKS', 'name' => 'Rencana Kerja dan Syarat'],
            ['code' => 'TOR', 'name' => 'Term of Reference'],
            ['code' => 'DR', 'name' => 'Dokumen Resmi Pendukung'],
            ['code' => 'FP', 'name' => 'Form Permintaan'],
            ['code' => 'FPP', 'name' => 'Form Permintaan Pembayaran'],
            ['code' => 'FPO', 'name' => 'Form Permintaan Order'],
            ['code' => 'FHR', 'name' => 'Form Hasil Review'],
        ];

        // Replace the previous set entirely -- keep only what's listed
        // above, since it's the authoritative company letter code list.
        LetterCode::whereNotIn('code', array_column($codes, 'code'))->delete();

        foreach ($codes as $code) {
            LetterCode::updateOrCreate(['code' => $code['code']], $code);
        }
    }
}
