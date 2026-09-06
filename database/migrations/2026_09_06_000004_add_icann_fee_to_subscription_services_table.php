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
        // Optional pass-through registrar fee -- only domain services
        // typically carry one, so it's nullable rather than required on
        // every service. Not part of the VAT/PPN taxable base (see
        // SubscriptionController::generateInvoice()), matching how
        // Invoice::admin_fee is already added post-tax.
        Schema::table('subscription_services', function (Blueprint $table) {
            $table->decimal('icann_fee', 15, 2)->nullable()->after('ppn_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_services', function (Blueprint $table) {
            $table->dropColumn('icann_fee');
        });
    }
};
