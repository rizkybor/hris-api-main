<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class FilesCompanySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('files_companies')->insert([
            [
                'document_name' => 'Akta Pendirian Perusahaan',
                'document_path' => 'documents/akta_pendirian.pdf',
                'type_file'     => 'pdf',
                'size_file'     => '1.2 MB',
                'description'   => 'Dokumen resmi akta pendirian perusahaan',
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ],
            [
                'document_name' => 'NPWP Perusahaan',
                'document_path' => 'documents/npwp_perusahaan.pdf',
                'type_file'     => 'pdf',
                'size_file'     => '500 KB',
                'description'   => 'Dokumen NPWP perusahaan',
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ],
            [
                'document_name' => 'SIUP',
                'document_path' => 'documents/siup.pdf',
                'type_file'     => 'pdf',
                'size_file'     => '750 KB',
                'description'   => 'Surat Izin Usaha Perdagangan',
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ],
        ]);
    }
}
