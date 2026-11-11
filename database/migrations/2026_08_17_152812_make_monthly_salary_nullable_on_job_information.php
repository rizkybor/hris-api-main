<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('job_information', fn (Blueprint $table) => $table->decimal('monthly_salary', 12, 2)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE job_information MODIFY monthly_salary DECIMAL(12,2) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('job_information', fn (Blueprint $table) => $table->decimal('monthly_salary', 12, 2)->change());
        } else {
            DB::statement('ALTER TABLE job_information MODIFY monthly_salary DECIMAL(12,2) NOT NULL');
        }
    }
};
