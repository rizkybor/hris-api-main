<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A project task can have several assignees. project_tasks.assignee_id
 * stays as the first (primary) assignee for older readers; this table is
 * the full list. Existing assignees are copied over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_task_id', 'employee_id']);
            $table->index('employee_id');
        });

        $now = now();
        DB::table('project_tasks')
            ->whereNotNull('assignee_id')
            ->whereIn('assignee_id', DB::table('employee_profiles')->select('id'))
            ->orderBy('id')
            ->select(['id', 'assignee_id'])
            ->chunk(500, function ($tasks) use ($now) {
                DB::table('project_task_assignees')->insertOrIgnore($tasks->map(fn ($t) => [
                    'project_task_id' => $t->id,
                    'employee_id' => $t->assignee_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_assignees');
    }
};
