<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            // Salary after attendance deduction, before BPJS/tax deductions.
            $table->decimal('gross_salary', 12, 2)->default(0)->after('original_salary');

            // Employee-borne BPJS contributions (deducted from take-home pay).
            $table->decimal('bpjs_kesehatan_employee', 12, 2)->default(0)->after('gross_salary');
            $table->decimal('bpjs_jht_employee', 12, 2)->default(0)->after('bpjs_kesehatan_employee');
            $table->decimal('bpjs_jp_employee', 12, 2)->default(0)->after('bpjs_jht_employee');

            // Company-borne BPJS contributions (informational, does not affect take-home pay).
            $table->decimal('bpjs_kesehatan_company', 12, 2)->default(0)->after('bpjs_jp_employee');
            $table->decimal('bpjs_jht_company', 12, 2)->default(0)->after('bpjs_kesehatan_company');
            $table->decimal('bpjs_jp_company', 12, 2)->default(0)->after('bpjs_jht_company');
            $table->decimal('bpjs_jkk_company', 12, 2)->default(0)->after('bpjs_jp_company');
            $table->decimal('bpjs_jkm_company', 12, 2)->default(0)->after('bpjs_jkk_company');

            $table->decimal('pph21', 12, 2)->default(0)->after('bpjs_jkm_company');
            $table->decimal('total_deduction', 12, 2)->default(0)->after('pph21');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn([
                'gross_salary',
                'bpjs_kesehatan_employee',
                'bpjs_jht_employee',
                'bpjs_jp_employee',
                'bpjs_kesehatan_company',
                'bpjs_jht_company',
                'bpjs_jp_company',
                'bpjs_jkk_company',
                'bpjs_jkm_company',
                'pph21',
                'total_deduction',
            ]);
        });
    }
};
