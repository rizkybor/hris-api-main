<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a cover letter encloses ("1 (satu) berkas Laporan Hasil UAT ke-1"), shown as Lampiran. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->string('enclosure', 255)->nullable()->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn('enclosure');
        });
    }
};
