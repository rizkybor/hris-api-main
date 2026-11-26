<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Surat Pengantar" (SPG) letter code for the cover letter sent with a
 * project progress report, and an optional link from a letter to the
 * project it was written for (so the export can offer the last one again).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('letter_codes')->where('code', 'SPG')->exists()) {
            DB::table('letter_codes')->insert([
                'code' => 'SPG',
                'name' => 'Surat Pengantar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('letters', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('employee_id')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        // Letters may already be numbered with SPG; keep the code.
    }
};
