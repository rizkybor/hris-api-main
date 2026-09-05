<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `supplier_vendor_id` pointed at the vendors table, which is being
     * renamed to `clients` in this same release -- but a hardware/asset
     * supplier is a genuinely different (money-out) relationship than a
     * client (money-in), so it's repointed at the new `suppliers` table
     * instead of following the vendors->clients rename.
     */
    public function up(): void
    {
        Schema::table('company_assets', function (Blueprint $table) {
            $table->dropForeign(['supplier_vendor_id']);
        });

        Schema::table('company_assets', function (Blueprint $table) {
            $table->renameColumn('supplier_vendor_id', 'supplier_id');
        });

        Schema::table('company_assets', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_assets', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('company_assets', function (Blueprint $table) {
            $table->renameColumn('supplier_id', 'supplier_vendor_id');
        });

        Schema::table('company_assets', function (Blueprint $table) {
            $table->foreign('supplier_vendor_id')->references('id')->on('vendors')->nullOnDelete();
        });
    }
};
