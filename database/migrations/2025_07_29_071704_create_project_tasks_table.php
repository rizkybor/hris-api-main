<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('employee_profiles')->onDelete('set null');
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes (project_id and assignee_id already indexed by foreign keys)
            $table->index('priority');
            $table->index('status');
            $table->index('due_date');
            $table->index(['project_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
