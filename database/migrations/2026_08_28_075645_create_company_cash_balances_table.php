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
        // Deliberately a separate, single-row table rather than reusing
        // company_finances.saldo_company -- that column has no singleton
        // guarantee (two existing code paths already disagree on which row
        // is "the" balance via first() vs latest()) and represents a
        // mutable current-state snapshot, not an immutable ledger opening
        // balance. CompanyCashBalance::current() always operates on the
        // single row with the lowest id, creating it with 0 if missing.
        Schema::create('company_cash_balances', function (Blueprint $table) {
            $table->id();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_cash_balances');
    }
};
