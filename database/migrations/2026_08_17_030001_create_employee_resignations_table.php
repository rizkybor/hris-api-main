<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('type'); // resign, terminated
            $table->text('reason')->nullable();
            $table->date('resignation_date');
            $table->date('last_working_date')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->string('status')->default('pending'); // pending, completed
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_resignations');
    }
};
