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
        Schema::create('project_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            // Indonesian "buku kas" convention: debit = money going in
            // (e.g. an additional budget top-up), credit = money going out
            // of the project's cash (operational spending/pemakaian) --
            // the running/closing balance is computed at query time from
            // Project.budget as the opening balance plus every debit minus
            // every credit, not stored on this table.
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
        Schema::dropIfExists('project_cash_transactions');
    }
};
