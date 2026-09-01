<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // THR (Tunjangan Hari Raya) is its own payroll run for the same
            // month as a regular salary run, so the old unique('salary_month')
            // has to widen to (salary_month, type) instead.
            $table->dropUnique(['salary_month']);
            $table->string('type')->default('monthly')->after('salary_month');
            $table->unique(['salary_month', 'type']);
        });

        Schema::table('payroll_details', function (Blueprint $table) {
            // Only meaningful on a 'thr' row -- the proration basis (masa
            // kerja dalam bulan, capped at 12) behind gross_salary there.
            $table->unsignedSmallInteger('months_of_service')->nullable()->after('absent_days');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn('months_of_service');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['salary_month', 'type']);
            $table->dropColumn('type');
            $table->unique('salary_month');
        });
    }
};
