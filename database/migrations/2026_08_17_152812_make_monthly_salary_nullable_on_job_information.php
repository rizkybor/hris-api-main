<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Monthly Salary is optional (e.g. unpaid interns), so this column must
     * allow NULL. doctrine/dbal isn't installed, so this uses raw ALTER
     * TABLE instead of Blueprint::change().
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE job_information MODIFY monthly_salary DECIMAL(12,2) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE job_information MODIFY monthly_salary DECIMAL(12,2) NOT NULL');
    }
};
