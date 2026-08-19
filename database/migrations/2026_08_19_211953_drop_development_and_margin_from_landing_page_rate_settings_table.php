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
        Schema::table('landing_page_rate_settings', function (Blueprint $table) {
            // Only Server/Design pricing stays configurable -- Development
            // rate and Margin Jual are now fixed defaults baked into
            // ProjectCalculatorService, still editable per-calculation in
            // the Create/Edit form, just no longer a company-wide setting.
            $table->dropColumn(['default_rate_developer', 'margin_percent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_page_rate_settings', function (Blueprint $table) {
            $table->decimal('default_rate_developer', 15, 2)->default(100000)->after('design_template_price');
            $table->decimal('margin_percent', 5, 2)->default(30)->after('default_rate_developer');
        });
    }
};
