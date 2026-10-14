<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_calculations', function (Blueprint $table) {
            // How many people actually work on this project (Feature/Build
            // scenarios). The duration estimate uses their share of the
            // team's capacity instead of the whole team's. Null on estimates
            // saved before this existed = the old whole-team assumption.
            $table->unsignedSmallInteger('allocated_developers')->nullable()->after('estimated_duration_weeks');
        });
    }

    public function down(): void
    {
        Schema::table('project_calculations', function (Blueprint $table) {
            $table->dropColumn('allocated_developers');
        });
    }
};
