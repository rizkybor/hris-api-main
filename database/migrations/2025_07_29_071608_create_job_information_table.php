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
        Schema::create('job_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->onDelete('cascade');
            $table->string('job_title');
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->integer('years_experience');
            $table->string('status')->default('active');
            $table->string('employment_type');
            $table->string('work_location');
            $table->date('start_date');
            $table->decimal('monthly_salary', 12, 2);
            $table->string('skill_level');
            $table->timestamps();
            $table->softDeletes();

            // Indexes (employee_id and team_id already indexed by foreign keys)
            $table->index('status');
            $table->index('employment_type');
            $table->index('work_location');
            $table->index('start_date');
            $table->index(['employee_id', 'status']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_information');
    }
};
