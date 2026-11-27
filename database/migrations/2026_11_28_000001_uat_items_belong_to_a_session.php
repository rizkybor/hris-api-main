<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Findings and notes now belong to a session (place) of a UAT, not to the
 * UAT itself. Every UAT gets at least one session (its name is optional,
 * shown as "Sesi 1"), and items without a session move to the UAT's first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_uat_locations', function (Blueprint $table) {
            $table->string('location', 150)->nullable()->change();
        });

        $now = now();
        DB::table('project_uat_sessions')->orderBy('id')->get(['id', 'date'])->each(function ($uat) use ($now) {
            $first = DB::table('project_uat_locations')->where('project_uat_session_id', $uat->id)->orderBy('position')->orderBy('id')->value('id');
            if (! $first) {
                $first = DB::table('project_uat_locations')->insertGetId([
                    'project_uat_session_id' => $uat->id,
                    'position' => 0,
                    'location' => null,
                    'held_on' => $uat->date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('project_uat_items')
                ->where('project_uat_session_id', $uat->id)
                ->whereNull('project_uat_location_id')
                ->update(['project_uat_location_id' => $first]);
        });
    }

    public function down(): void
    {
        // Sessions without a name were created by up(); their items keep
        // pointing at them (the column stays nullable either way).
        DB::table('project_uat_locations')->whereNull('location')->update(['location' => 'Sesi']);
        Schema::table('project_uat_locations', function (Blueprint $table) {
            $table->string('location', 150)->nullable(false)->change();
        });
    }
};
