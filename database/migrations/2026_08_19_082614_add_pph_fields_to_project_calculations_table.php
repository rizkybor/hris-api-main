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
        Schema::table('project_calculations', function (Blueprint $table) {
            // PPh (Indonesian withholding income tax) is withheld by the
            // client from what they pay the vendor and remitted straight to
            // the tax office -- unlike PPN, which the vendor collects on
            // top of the price. Kept separate from the PPN columns since
            // both can apply independently and net_received (what the
            // vendor actually gets in cash) needs both to compute.
            $table->boolean('include_pph')->default(false)->after('total_with_ppn');
            $table->string('pph_type')->nullable()->after('include_pph');
            $table->decimal('pph_percent', 5, 2)->nullable()->after('pph_type');
            $table->decimal('pph_amount', 15, 2)->nullable()->after('pph_percent');
            $table->decimal('net_received', 15, 2)->nullable()->after('pph_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_calculations', function (Blueprint $table) {
            $table->dropColumn(['include_pph', 'pph_type', 'pph_percent', 'pph_amount', 'net_received']);
        });
    }
};
