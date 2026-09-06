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
        // Superseded by subscription_services (one row per service) --
        // data was already backfilled there by the previous migration.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'product_name', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('service_type')->after('name');
            $table->string('product_name')->nullable()->after('service_type');
            $table->decimal('amount', 15, 2)->after('billing_cycle');
        });
    }
};
