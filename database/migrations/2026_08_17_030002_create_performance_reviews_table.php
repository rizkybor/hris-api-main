<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('period'); // e.g. "Q1 2026"
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('overall_rating', 3, 2); // 1.00 - 5.00
            $table->json('category_scores')->nullable(); // { productivity: 4, quality: 5, ... }
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('goals_next_period')->nullable();
            $table->string('status')->default('submitted'); // submitted, acknowledged
            $table->timestamp('employee_acknowledged_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'period']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
