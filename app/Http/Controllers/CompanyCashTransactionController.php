<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompanyCashTransactionResource;
use App\Models\CompanyCashBalance;
use App\Models\CompanyCashTransaction;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CompanyCashTransactionController extends Controller implements HasMiddleware
{
    public function __construct(protected CloudinaryManager $cloudinary) {}

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
     * The company-wide cash book, newest first, with a running balance
     * computed against the configured opening balance -- includes both
     * manual entries and every project's cash ledger entry, auto-mirrored
     * in (see CompanyCashLedgerSyncService), so nothing needs recording
     * twice by hand.
     *
     * Optionally narrowed (project, type, source, date range, search), but the balance
     * math always walks the FULL chronological history first -- each row's
     * `balance` is the true company-wide running total at that point in
     * time, not a value recomputed in isolation from only that project's
     * entries (which would be a different, less meaningful number). Only
     * the *displayed* rows are filtered down, plus the total_debit/
     * total_credit summary, which does reflect just the filtered set.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'type' => ['nullable', 'in:debit,credit'],
            'source' => ['nullable', 'in:manual,project'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'row_per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $page = (int) ($validated['page'] ?? 1);
            $rowPerPage = (int) ($validated['row_per_page'] ?? 15);

            $allTransactions = CompanyCashTransaction::with(['creator', 'project'])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $balanceRow = CompanyCashBalance::current();
            $openingBalance = (float) $balanceRow->opening_balance;
            $runningBalance = $openingBalance;

            // Indonesian "buku kas" convention: Debit = uang masuk, Kredit
            // = uang keluar/pemakaian -- matches the project cash ledger.
            foreach ($allTransactions as $transaction) {
                $amount = (float) $transaction->amount;
                $runningBalance += $transaction->type === 'debit' ? $amount : -$amount;
                $transaction->running_balance = $runningBalance;
            }

            $closingBalance = $runningBalance;

            $filtered = $allTransactions;
            if (! empty($validated['project_id'])) {
                $filtered = $filtered->where('project_id', $validated['project_id']);
            }
            if (! empty($validated['type'])) {
                $filtered = $filtered->where('type', $validated['type']);
            }
            if (! empty($validated['source'])) {
                $filtered = $filtered->filter(fn ($t) => $t->isSynced() === ($validated['source'] === 'project'));
            }
            if (! empty($validated['start_date'])) {
                $filtered = $filtered->filter(fn ($t) => $t->transaction_date->toDateString() >= $validated['start_date']);
            }
            if (! empty($validated['end_date'])) {
                $filtered = $filtered->filter(fn ($t) => $t->transaction_date->toDateString() <= $validated['end_date']);
            }
            if (! empty($validated['search'])) {
                $needle = mb_strtolower($validated['search']);
                $filtered = $filtered->filter(fn ($t) => str_contains(mb_strtolower($t->description.' '.$t->notes.' '.$t->project?->name), $needle));
            }
            $filtered = $filtered->values();

            $totalDebit = (float) $filtered->where('type', 'debit')->sum(fn ($t) => (float) $t->amount);
            $totalCredit = (float) $filtered->where('type', 'credit')->sum(fn ($t) => (float) $t->amount);

            $newestFirst = $filtered->reverse()->values();
            $total = $newestFirst->count();
            $page = min($page, (int) max(1, ceil($total / $rowPerPage)));
            $paged = $newestFirst->slice(($page - 1) * $rowPerPage, $rowPerPage)->values();

            return ResponseHelper::jsonResponse(true, 'Company Cash Transactions Retrieved Successfully', [
                'items' => CompanyCashTransactionResource::collection($paged),
                'opening_balance' => $openingBalance,
                ...$this->openingBalanceStamp($balanceRow),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $closingBalance,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => (int) max(1, ceil($total / $rowPerPage)),
                    'per_page' => $rowPerPage,
                    'total' => $total,
                    'from' => $total === 0 ? null : ($page - 1) * $rowPerPage + 1,
                    'to' => $total === 0 ? null : min($page * $rowPerPage, $total),
                ],
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
            'proof' => self::PROOF_RULE,
        ], [], self::PROOF_ATTRIBUTES);

        try {
            $transaction = CompanyCashTransaction::create([
                ...collect($validated)->except('proof')->all(),
                ...$this->storeProof($request),
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
            'proof' => self::PROOF_RULE,
            'remove_proof' => ['nullable', 'boolean'],
        ], [], self::PROOF_ATTRIBUTES);

        try {
            $transaction = CompanyCashTransaction::findOrFail($id);

            if ($transaction->isSynced()) {
                return ResponseHelper::jsonResponse(false, 'This entry was recorded from a project\'s cash ledger -- edit it there instead, it will sync here automatically.', null, 422);
            }

            $previousProof = $transaction->proof_file;
            $proofChange = $request->hasFile('proof')
                ? $this->storeProof($request)
                : ($request->boolean('remove_proof') ? ['proof_file' => null, 'proof_name' => null, 'proof_mime' => null] : []);

            $transaction->update([
                ...collect($validated)->except(['proof', 'remove_proof'])->all(),
                ...$proofChange,
            ]);

            // The old file goes once the row no longer points at it.
            if ($previousProof && array_key_exists('proof_file', $proofChange)) {
                $this->deleteProofFile($previousProof);
            }

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

    // Optional proof of transaction: a photo/scan or PDF of the receipt.
    private const PROOF_RULE = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    private const PROOF_ATTRIBUTES = ['proof' => 'Bukti Transaksi'];

    /** Uploads the request's proof file, if any, and returns its columns. */
    private function storeProof(Request $request): array
    {
        if (! $request->hasFile('proof')) {
            return [];
        }

        $file = $request->file('proof');
        $publicId = $this->cloudinary->uploadAuto(
            $file,
            CloudinaryFolders::companyFiles('cash-book'),
            CloudinaryFolders::filename('cash-proof')
        );

        return [
            'proof_file' => $publicId,
            'proof_name' => $file->getClientOriginalName(),
            'proof_mime' => $file->getMimeType(),
        ];
    }

    private function deleteProofFile(string $publicId): void
    {
        try {
            $this->cloudinary->delete($publicId, str_ends_with(strtolower($publicId), '.pdf') ? 'raw' : 'image');
        } catch (\Throwable $e) {
            // A leftover file on Cloudinary isn't worth failing the edit over.
        }
    }

    /**
     * Sets the ledger's starting balance -- gated by the same permission
     * as recording a manual entry (company-cash-book-create), since it's
     * conceptually the same "who may touch the company's cash book by
     * hand" access.
     */
    /** When the opening balance was last set, and by whom (null if never set). */
    private function openingBalanceStamp(CompanyCashBalance $balance): array
    {
        $set = $balance->updated_by !== null;

        return [
            'opening_balance_updated_at' => $set ? $balance->updated_at?->toIso8601String() : null,
            'opening_balance_updated_by' => $set ? $balance->updater?->name : null,
        ];
    }

    public function updateOpeningBalance(Request $request)
    {
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $balance = CompanyCashBalance::current();
            $balance->fill([
                'opening_balance' => $validated['opening_balance'],
                'updated_by' => $request->user()->id,
            ]);
            // Saving the same figure again still counts as "checked today".
            $balance->isDirty() ? $balance->save() : $balance->touch();

            return ResponseHelper::jsonResponse(true, 'Opening Balance Updated Successfully', [
                'opening_balance' => (float) $balance->opening_balance,
                ...$this->openingBalanceStamp($balance),
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
