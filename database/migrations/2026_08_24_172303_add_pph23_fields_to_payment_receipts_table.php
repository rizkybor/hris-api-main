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
        Schema::table('payment_receipts', function (Blueprint $table) {
            // PPh 23 is withheld by the client from what they pay -- unlike
            // PPN, which is collected on top. `amount` keeps its existing
            // meaning (what was actually received, net of any withholding);
            // these columns record the withholding itself so the gross
            // invoiced amount (amount + pph23_amount) and the report/PDF
            // breakdown can be reconstructed. Null when no PPh 23 applies.
            $table->string('pph23_type')->nullable()->after('amount');
            $table->decimal('pph23_percent', 5, 2)->nullable()->after('pph23_type');
            $table->decimal('pph23_amount', 15, 2)->nullable()->after('pph23_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropColumn(['pph23_type', 'pph23_percent', 'pph23_amount']);
        });
    }
};
