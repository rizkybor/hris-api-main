<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spending: a manual cash book entry recorded on behalf of an employee
     * (e.g. Rizky, Andy, Aldi). It is a Debit row like any other; category
     * marks it as spending and employee_id says on whose behalf.
     */
    public function up(): void
    {
        Schema::table('company_cash_transactions', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('type');
            $table->unsignedBigInteger('employee_id')->nullable()->after('category');

            $table->index('category');
            $table->foreign('employee_id')->references('id')->on('employee_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'employee_id']);
        });
    }
};
