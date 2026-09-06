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
        Schema::table('invoices', function (Blueprint $table) {
            // Only populated for invoices generated from a Subscription --
            // "September 2026" for a monthly subscription, "2026" for a
            // yearly one -- so the same-period duplicate check in
            // SubscriptionController::generateInvoice() doesn't have to
            // parse it back out of the free-text item description.
            $table->string('billing_period')->nullable()->after('subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
