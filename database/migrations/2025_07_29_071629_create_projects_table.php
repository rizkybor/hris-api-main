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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('planning');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->foreignId('project_leader_id')->nullable()->constrained('employee_profiles')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes (project_leader_id already indexed by foreign key)
            $table->index('name');
            $table->index('type');
            $table->index('priority');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['status', 'priority']);
            $table->index(['start_date', 'end_date']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
