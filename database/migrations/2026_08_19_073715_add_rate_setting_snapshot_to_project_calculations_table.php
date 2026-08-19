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
        Schema::table('project_calculations', function (Blueprint $table) {
            // Full Team Rate Setup breakdown (selected fixed costs, SDM
            // resources, infrastructure tools, margin multiplier, etc.) as it
            // stood when this estimate was saved -- rate_cost_per_hour/
            // rate_sell_per_hour already snapshot the two resulting numbers,
            // but not the underlying setup that produced them. Frozen here
            // for the same reason: a later change to the shared, global
            // ProjectRateSetting must never silently rewrite a past quote.
            $table->json('rate_setting_snapshot')->nullable()->after('infra_setup_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_calculations', function (Blueprint $table) {
            $table->dropColumn('rate_setting_snapshot');
        });
    }
};
