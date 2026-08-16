<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_information', function (Blueprint $table) {
            $table->date('probation_end_date')->nullable()->after('start_date');
            $table->date('contract_end_date')->nullable()->after('probation_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('job_information', function (Blueprint $table) {
            $table->dropColumn(['probation_end_date', 'contract_end_date']);
        });
    }
};
