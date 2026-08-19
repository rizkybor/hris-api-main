<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('team_assignment_mode')->default('employee')->after('project_leader_id');
        });

        // Backfill: a project that already has a Team attached was clearly
        // assigned "by Team" under the old single-mode flow; everything
        // else defaults to "employee" (individually-picked members).
        DB::table('projects')
            ->whereIn('id', DB::table('project_teams')->select('project_id'))
            ->update(['team_assignment_mode' => 'team']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('team_assignment_mode');
        });
    }
};
