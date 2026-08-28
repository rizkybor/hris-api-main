<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each row is one staff member's own progress on a task -- status lives
 * here per-assignee (not on staff_tasks itself), since a task assigned to
 * several people has each of them independently marking their own
 * to_do/in_progress/done, not one shared status for the whole task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('status')->default('todo'); // todo, in_progress, done
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_task_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_task_assignees');
    }
};
