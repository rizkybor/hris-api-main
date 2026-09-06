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
        Schema::table('payroll_details', function (Blueprint $table) {
            // 'manual' = final_salary/gross_salary typed in directly (the
            // long-standing default); 'project_percentage' = derived from a
            // cut of a Project's budget instead of the attendance-based
            // formula -- for startups where some staff are paid a percentage
            // of what a project brings in rather than a fixed figure.
            $table->string('payment_mode')->default('manual')->after('notes');
            $table->foreignId('source_project_id')->nullable()->after('payment_mode')->constrained('projects')->nullOnDelete();
            $table->decimal('project_percentage', 5, 2)->nullable()->after('source_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_project_id');
            $table->dropColumn(['payment_mode', 'project_percentage']);
        });
    }
};
