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
        Schema::table('projects', function (Blueprint $table) {
            // Both optional -- a project may have no warranty/retention
            // terms at all. Expressed as a duration (months) rather than
            // an absolute date since it's naturally relative to the
            // project's end_date, matching the warranty_months convention
            // already used on purchase_orders.
            $table->unsignedSmallInteger('warranty_period_months')->nullable()->after('budget');
            $table->unsignedSmallInteger('retention_period_months')->nullable()->after('warranty_period_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['warranty_period_months', 'retention_period_months']);
        });
    }
};
