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
            // Purely informational -- lets an invoice note that the client
            // is expected to withhold PPh 23 when they pay, so `total`
            // stays the actual invoiced amount and is never reduced by
            // this. The real withholding is recorded on the Payment
            // Receipt (pph23_type/pph23_percent/pph23_amount there) once
            // payment actually happens; this is just an advance heads-up.
            $table->string('pph23_type')->nullable()->after('terms');
            $table->decimal('pph23_percent', 5, 2)->nullable()->after('pph23_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pph23_type', 'pph23_percent']);
        });
    }
};
