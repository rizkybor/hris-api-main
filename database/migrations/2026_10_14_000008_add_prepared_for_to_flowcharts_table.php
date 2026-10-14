<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            // Who the flowchart is prepared for (a client, a team...), shown
            // in the exported document's details and licence line.
            $table->string('prepared_for')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            $table->dropColumn('prepared_for');
        });
    }
};
