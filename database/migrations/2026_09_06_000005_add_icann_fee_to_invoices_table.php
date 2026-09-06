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
        // Aggregate of every bundled service's icann_fee at the moment an
        // invoice is generated -- added post-tax alongside admin_fee, not
        // part of subtotal/ppn_amount (the VAT taxable base).
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('icann_fee', 15, 2)->default(0)->after('admin_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('icann_fee');
        });
    }
};
