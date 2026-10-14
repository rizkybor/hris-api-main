<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operational Cost items now share one usage status -- "active" (in use by
 * JC Digital, counted in the totals) or "inactive" (kept in the list, not
 * counted) -- and an expiry date that's required while an item is in use.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fixed_costs', 'sdm_resources'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Existing rows start as "in use": until now every one of
                // them was counted in the totals, so this keeps the totals
                // unchanged until someone marks an item as not used.
                $table->string('status')->default('active')->after('actual');
                $table->date('expired_date')->nullable()->after('status');
                $table->index('status');
            });
        }

        // Not-in-use tools no longer need an expiry date.
        Schema::table('infrastructure_tools', function (Blueprint $table) {
            $table->date('expired_date')->nullable()->change();
        });

        // The two extra "active" flavours fold into plain "in use".
        DB::table('infrastructure_tools')
            ->whereIn('status', ['active_monitoring_required', 'active_non_critical'])
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        foreach (['fixed_costs', 'sdm_resources'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn(['status', 'expired_date']);
            });
        }
        // infrastructure_tools.expired_date stays nullable: rows saved
        // without a date since this migration would break NOT NULL.
    }
};
