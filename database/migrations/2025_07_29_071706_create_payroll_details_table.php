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
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employee_profiles')->onDelete('cascade');
            $table->decimal('original_salary', 12, 2);
            $table->decimal('final_salary', 12, 2);
            $table->integer('attended_days')->default(0);
            $table->integer('sick_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes (payroll_id and employee_id already indexed by foreign keys)
            $table->index(['payroll_id', 'employee_id']);
            $table->unique(['payroll_id', 'employee_id']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
