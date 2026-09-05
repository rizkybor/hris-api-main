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
        // Recurring invoices generated from a subscription previously
        // hardcoded ppn/admin_fee to 0 and left bank details, terms, and
        // PPh 23 withholding blank -- unlike a manually-created Invoice,
        // which asks for all of this per document. Storing it on the
        // subscription itself means it's configured once and reused for
        // every period's invoice instead of missing every time.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('ppn_percentage', 5, 2)->default(0)->after('amount');
            $table->decimal('admin_fee', 15, 2)->default(0)->after('ppn_percentage');
            $table->string('bank_name')->nullable()->after('admin_fee');
            $table->text('terms')->nullable()->after('bank_name');
            $table->string('pph23_type')->nullable()->after('terms');
            $table->decimal('pph23_percent', 5, 2)->nullable()->after('pph23_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['ppn_percentage', 'admin_fee', 'bank_name', 'terms', 'pph23_type', 'pph23_percent']);
        });
    }
};
