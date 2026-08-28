<?php

namespace App\Services;

use App\Models\CompanyCashTransaction;
use App\Models\ProjectCashTransaction;

/**
 * Keeps the company-wide cash book's mirror of a project's cash ledger
 * entries in sync, so a project transaction is never recorded twice by
 * hand -- create/update/delete on ProjectCashTransaction calls straight
 * into here (see ProjectCashTransactionController), and the mirrored
 * company_cash_transactions row follows automatically.
 */
class CompanyCashLedgerSyncService
{
    /**
     * Create or update the mirror row for a project transaction --
     * idempotent, so it's safe to call from both store() and update().
     */
    public function sync(ProjectCashTransaction $transaction): CompanyCashTransaction
    {
        $transaction->loadMissing('project');

        return CompanyCashTransaction::updateOrCreate(
            ['project_cash_transaction_id' => $transaction->id],
            [
                'project_id' => $transaction->project_id,
                'type' => $transaction->type,
                'description' => '['.$transaction->project->name.'] '.$transaction->description,
                'amount' => $transaction->amount,
                'transaction_date' => $transaction->transaction_date,
                'notes' => $transaction->notes,
                'created_by' => $transaction->created_by,
            ]
        );
    }

    /**
     * Remove the mirror row when its source project transaction is
     * deleted -- soft-deletes, matching ProjectCashTransaction's own
     * SoftDeletes so both sides of the mirror stay consistent.
     */
    public function remove(ProjectCashTransaction $transaction): void
    {
        CompanyCashTransaction::where('project_cash_transaction_id', $transaction->id)->delete();
    }
}
