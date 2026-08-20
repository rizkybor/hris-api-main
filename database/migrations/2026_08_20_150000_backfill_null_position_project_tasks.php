<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tasks created before the `position` column existed are left NULL,
     * and MySQL sorts NULL before every real number in ASC order -- so a
     * single leftover NULL-position task permanently sits above anything
     * the priority-placement/drag logic computes, no matter how small.
     * One-time backfill: assign each NULL a sequential position (oldest
     * first) within its own (project_id, status) group, preserving the
     * order those tasks already appeared in.
     */
    public function up(): void
    {
        DB::table('project_tasks')
            ->whereNull('position')
            ->orderBy('project_id')
            ->orderBy('status')
            ->orderBy('created_at')
            ->get(['id', 'project_id', 'status'])
            ->groupBy(fn ($row) => $row->project_id.'|'.$row->status)
            ->each(function ($rows) {
                $position = 1000;
                foreach ($rows as $row) {
                    DB::table('project_tasks')->where('id', $row->id)->update(['position' => $position]);
                    $position += 1000;
                }
            });
    }

    /**
     * Not reversible -- there is no record of which rows were originally
     * NULL, and reverting would just reintroduce the bug this fixes.
     */
    public function down(): void
    {
        //
    }
};
