<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cash transaction can carry several proof files. The single
 * proof_file / proof_name / proof_mime columns are copied here and no
 * longer written.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A first run on MySQL could stop after CREATE TABLE (the default
        // foreign key name was 65 characters, over MySQL's 64 limit), leaving
        // an empty table behind without the migration being recorded.
        if (Schema::hasTable('company_cash_transaction_proofs') && DB::table('company_cash_transaction_proofs')->doesntExist()) {
            Schema::drop('company_cash_transaction_proofs');
        }

        Schema::create('company_cash_transaction_proofs', function (Blueprint $table) {
            $table->id();
            // Explicit short name: the generated one is 65 characters (MySQL max 64).
            $table->foreignId('company_cash_transaction_id')
                ->constrained('company_cash_transactions', 'id', 'cash_tx_proofs_transaction_fk')
                ->cascadeOnDelete();
            $table->string('file');
            $table->string('name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('company_cash_transactions')
            ->whereNotNull('proof_file')
            ->orderBy('id')
            ->select(['id', 'proof_file', 'proof_name', 'proof_mime'])
            ->chunk(500, function ($rows) use ($now) {
                DB::table('company_cash_transaction_proofs')->insert($rows->map(fn ($r) => [
                    'company_cash_transaction_id' => $r->id,
                    'file' => $r->proof_file,
                    'name' => $r->proof_name,
                    'mime' => $r->proof_mime,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_cash_transaction_proofs');
    }
};
