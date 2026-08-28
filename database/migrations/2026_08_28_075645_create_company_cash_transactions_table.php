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
        Schema::create('company_cash_transactions', function (Blueprint $table) {
            $table->id();
            // Both nullable -- set only when this row is an automatic
            // mirror of a project's own cash ledger entry (see
            // ProjectCashTransactionController::syncToCompanyLedger()).
            // Null on both = a manual, company-level entry not tied to any
            // project. Editing/deleting a synced row directly here is
            // blocked -- it must be changed on the project's own ledger,
            // which then re-syncs here, so the two never drift apart.
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('project_cash_transaction_id')->nullable();
            // Indonesian "buku kas" convention: debit = money in, credit =
            // money out -- matches project_cash_transactions.
            $table->enum('type', ['debit', 'credit']);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('set null');

            $table->foreign('project_cash_transaction_id')
                ->references('id')
                ->on('project_cash_transactions')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_cash_transactions');
    }
};
