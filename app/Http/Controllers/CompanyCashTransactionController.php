<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompanyCashTransactionResource;
use App\Models\CompanyCashBalance;
use App\Models\CompanyCashTransaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CompanyCashTransactionController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['company-cash-book-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['company-cash-book-create']), only: ['store', 'updateOpeningBalance']),
            new Middleware(PermissionMiddleware::using(['company-cash-book-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['company-cash-book-delete']), only: ['destroy']),
        ];
    }

    /**
     * The company-wide cash book, oldest first, with a running balance
     * computed against the configured opening balance -- includes both
     * manual entries and every project's cash ledger entry, auto-mirrored
     * in (see CompanyCashLedgerSyncService), so nothing needs recording
     * twice by hand.
     */
    public function index(Request $request)
    {
        try {
            $transactions = CompanyCashTransaction::with(['creator', 'project'])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $openingBalance = (float) CompanyCashBalance::current()->opening_balance;
            $runningBalance = $openingBalance;
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            // Indonesian "buku kas" convention: Debit = uang masuk, Kredit
            // = uang keluar/pemakaian -- matches the project cash ledger.
            foreach ($transactions as $transaction) {
                $amount = (float) $transaction->amount;

                if ($transaction->type === 'debit') {
                    $runningBalance += $amount;
                    $totalDebit += $amount;
                } else {
                    $runningBalance -= $amount;
                    $totalCredit += $amount;
                }

                $transaction->running_balance = $runningBalance;
            }

            return ResponseHelper::jsonResponse(true, 'Company Cash Transactions Retrieved Successfully', [
                'items' => CompanyCashTransactionResource::collection($transactions),
                'opening_balance' => $openingBalance,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $runningBalance,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Manual entries only -- project_id/project_cash_transaction_id are
     * never accepted from the request, only ever set by
     * CompanyCashLedgerSyncService when mirroring a project transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:debit,credit'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $transaction = CompanyCashTransaction::create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Recorded Successfully', new CompanyCashTransactionResource($transaction->load('creator')), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'required', 'string', 'in:debit,credit'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $transaction = CompanyCashTransaction::findOrFail($id);

            if ($transaction->isSynced()) {
                return ResponseHelper::jsonResponse(false, 'This entry was recorded from a project\'s cash ledger -- edit it there instead, it will sync here automatically.', null, 422);
            }

            $transaction->update($validated);

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Updated Successfully', new CompanyCashTransactionResource($transaction->load('creator')), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Cash Transaction Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $transaction = CompanyCashTransaction::findOrFail($id);

            if ($transaction->isSynced()) {
                return ResponseHelper::jsonResponse(false, 'This entry was recorded from a project\'s cash ledger -- delete it there instead, it will sync here automatically.', null, 422);
            }

            $transaction->delete();

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Cash Transaction Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Sets the ledger's starting balance -- gated by the same permission
     * as recording a manual entry (company-cash-book-create), since it's
     * conceptually the same "who may touch the company's cash book by
     * hand" access.
     */
    public function updateOpeningBalance(Request $request)
    {
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $balance = CompanyCashBalance::current();
            $balance->update([
                'opening_balance' => $validated['opening_balance'],
                'updated_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Opening Balance Updated Successfully', [
                'opening_balance' => (float) $balance->opening_balance,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
