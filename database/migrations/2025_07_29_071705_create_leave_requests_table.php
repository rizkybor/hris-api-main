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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->onDelete('cascade');
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->text('reason')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('employee_profiles')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes (employee_id and approved_by already indexed by foreign keys)
            $table->index('leave_type');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['status', 'start_date']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
