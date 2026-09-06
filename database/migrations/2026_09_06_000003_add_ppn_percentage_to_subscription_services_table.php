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
        // Optional per-service override -- when a subscription bundles
        // several services taxed at different rates, each can set its own
        // VAT/PPN% instead of one flat rate for the whole invoice. Left
        // null, a service contributes 0% (see
        // SubscriptionController::recalculateAggregatePpn()).
        Schema::table('subscription_services', function (Blueprint $table) {
            $table->decimal('ppn_percentage', 5, 2)->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_services', function (Blueprint $table) {
            $table->dropColumn('ppn_percentage');
        });
    }
};
