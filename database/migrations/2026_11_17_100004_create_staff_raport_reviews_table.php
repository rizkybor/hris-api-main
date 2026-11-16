<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional closing notes on an employee's Staff Report, one per reviewer
 * (Manager / Operational Director / Finance Manager). Printed on the PDF and
 * shown on the employee's Performance tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_raport_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles', 'id', 'staff_raport_reviews_employee_fk')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users', 'id', 'staff_raport_reviews_reviewer_fk')->cascadeOnDelete();
            $table->text('review');
            $table->timestamps();

            $table->unique(['employee_id', 'reviewer_id'], 'staff_raport_reviews_employee_reviewer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_raport_reviews');
    }
};
