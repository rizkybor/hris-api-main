<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project runs in two phases: Development (start_date - end_date) and
     * then Retention (retention_start_date - retention_end_date). Projects
     * that only had a retention length in months get dates right after
     * their development end.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('retention_start_date')->nullable()->after('end_date');
            $table->date('retention_end_date')->nullable()->after('retention_start_date');
        });

        DB::table('projects')
            ->whereNotNull('end_date')
            ->where('retention_period_months', '>', 0)
            ->orderBy('id')
            ->get(['id', 'end_date', 'retention_period_months'])
            ->each(function ($project) {
                $start = Carbon::parse($project->end_date)->addDay();
                DB::table('projects')->where('id', $project->id)->update([
                    'retention_start_date' => $start->toDateString(),
                    'retention_end_date' => $start->copy()->addMonthsNoOverflow((int) $project->retention_period_months)->subDay()->toDateString(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['retention_start_date', 'retention_end_date']);
        });
    }
};
