<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('company_assets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assigned_at');
            $table->date('returned_at')->nullable();
            $table->string('condition_at_assignment')->nullable();
            $table->string('condition_at_return')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignment_histories');
    }
};
