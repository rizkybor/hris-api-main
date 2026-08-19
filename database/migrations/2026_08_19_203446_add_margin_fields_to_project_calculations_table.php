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
            // Landing Page scenario's own sell margin -- separate from
            // Feature/Build's margin_multiplier (which is baked into
            // rate_sell_per_hour long before this row exists), since Landing
            // Page's Rp-based package pricing has no hourly rate to apply a
            // multiplier to.
            $table->decimal('margin_percent', 5, 2)->nullable()->after('pm_overhead_total');
            $table->decimal('margin_total', 15, 2)->default(0)->after('margin_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_calculations', function (Blueprint $table) {
            $table->dropColumn(['margin_percent', 'margin_total']);
        });
    }
};
