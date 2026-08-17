<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_rate_settings', function (Blueprint $table) {
            // Total Biaya Operasional Tim / Bulan, and the productive hours
            // that go with it, are no longer typed in by hand -- they're
            // derived from whichever Fixed Cost / SDM Resource /
            // Infrastructure Tool line items get selected here.
            $table->json('selected_fixed_cost_ids')->nullable()->after('id');
            $table->json('selected_sdm_resource_ids')->nullable()->after('selected_fixed_cost_ids');
            $table->json('selected_infrastructure_tool_ids')->nullable()->after('selected_sdm_resource_ids');

            $table->dropColumn(['team_monthly_cost', 'productive_hours_per_person', 'team_size']);
        });
    }

    public function down(): void
    {
        Schema::table('project_rate_settings', function (Blueprint $table) {
            $table->dropColumn([
                'selected_fixed_cost_ids',
                'selected_sdm_resource_ids',
                'selected_infrastructure_tool_ids',
            ]);

            $table->decimal('team_monthly_cost', 15, 2)->default(0);
            $table->decimal('productive_hours_per_person', 8, 2)->default(0);
            $table->unsignedInteger('team_size')->default(0);
        });
    }
};
