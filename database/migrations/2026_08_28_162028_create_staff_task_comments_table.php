<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('staff_task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->json('mentioned_employee_ids')->nullable();
            $table->timestamps();

            $table->index(['staff_task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_task_comments');
    }
};
