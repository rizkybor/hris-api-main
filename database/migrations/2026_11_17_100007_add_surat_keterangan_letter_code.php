<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Surat Keterangan" (e.g. the employment reference issued when a contract
 * ends) as a letter code, so it can be numbered like any other letter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('letter_codes')->where('code', 'SKT')->exists()) {
            return;
        }
        DB::table('letter_codes')->insert([
            'code' => 'SKT',
            'name' => 'Surat Keterangan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Letters may already be numbered with this code; keep it.
    }
};
