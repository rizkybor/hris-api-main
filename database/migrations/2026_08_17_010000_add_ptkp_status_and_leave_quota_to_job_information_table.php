<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_information', function (Blueprint $table) {
            $table->string('ptkp_status')->default('TK/0')->after('monthly_salary');
            $table->integer('annual_leave_quota')->default(12)->after('ptkp_status');
        });
    }

    public function down(): void
    {
        Schema::table('job_information', function (Blueprint $table) {
            $table->dropColumn(['ptkp_status', 'annual_leave_quota']);
        });
    }
};
