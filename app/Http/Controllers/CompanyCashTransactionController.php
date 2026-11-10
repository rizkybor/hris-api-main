<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompanyCashTransactionResource;
use App\Models\CompanyCashBalance;
use App\Models\CompanyCashTransaction;
use App\Models\EmployeeProfile;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\CompanyCashBookService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CompanyCashTransactionController extends Controller implements HasMiddleware
{
    public function __construct(protected CloudinaryManager $cloudinary, protected CompanyCashBookService $cashBook) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['company-cash-book-list']), only: ['index', 'employeeOptions']),
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
            'category' => ['nullable', Rule::in(CompanyCashTransaction::CATEGORIES)],
            'employee_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'row_per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $page = (int) ($validated['page'] ?? 1);
            $rowPerPage = (int) ($validated['row_per_page'] ?? 15);

            $ledger = $this->cashBook->ledger($validated);
            $balanceRow = $ledger['balance_row'];
            $openingBalance = $ledger['opening_balance'];
            $closingBalance = $ledger['closing_balance'];
            $filtered = $ledger['rows'];

            $totalDebit = (float) $filtered->where('type', 'debit')->sum(fn ($t) => (float) $t->amount);
            $totalCredit = (float) $filtered->where('type', 'credit')->sum(fn ($t) => (float) $t->amount);
            // Spending is part of total_debit; reported on its own too so the
            // page can show Debit without and with spending.
            $totalSpending = (float) $filtered->filter(fn ($t) => $t->isSpending())->sum(fn ($t) => (float) $t->amount);

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
                'total_spending' => $totalSpending,
                'closing_balance' => $closingBalance,
                // Spending per person (on behalf of), over the filtered rows.
                'spending_by_employee' => $this->spendingByEmployee($filtered),
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
            ...self::SPENDING_RULES,
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'proof' => self::PROOF_RULE,
        ], [], self::PROOF_ATTRIBUTES);

        try {
            $transaction = CompanyCashTransaction::create([
                ...self::normalizeSpending(collect($validated)->except('proof')->all()),
                ...$this->storeProof($request),
                'created_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Recorded Successfully', new CompanyCashTransactionResource($transaction->load(['creator', 'employee.user'])), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'required', 'string', 'in:debit,credit'],
            ...self::SPENDING_RULES,
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

            $fields = collect($validated)->except(['proof', 'remove_proof'])->all();
            // A row that is (or becomes) spending stays a Debit with a person.
            $isSpending = array_key_exists('category', $fields) ? $fields['category'] === CompanyCashTransaction::CATEGORY_SPENDING : $transaction->isSpending();
            if ($isSpending && empty($fields['employee_id'] ?? $transaction->employee_id)) {
                return ResponseHelper::jsonResponse(false, 'Choose the employee this spending is on behalf of.', ['errors' => ['employee_id' => ['Choose the employee this spending is on behalf of.']]], 422);
            }

            $transaction->update([
                ...self::normalizeSpending($fields, $isSpending),
                ...$proofChange,
            ]);

            // The old file goes once the row no longer points at it.
            if ($previousProof && array_key_exists('proof_file', $proofChange)) {
                $this->deleteProofFile($previousProof);
            }

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Updated Successfully', new CompanyCashTransactionResource($transaction->load(['creator', 'employee.user'])), 200);
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
     * Spending: category "spending" with the employee it is on behalf of
     * (required then). Spending is always recorded as a Debit.
     */
    private const SPENDING_RULES = [
        'category' => ['nullable', 'string', 'in:spending'],
        'employee_id' => ['nullable', 'required_if:category,spending', 'integer', 'exists:employee_profiles,id'],
    ];

    private static function normalizeSpending(array $fields, ?bool $isSpending = null): array
    {
        $isSpending ??= ($fields['category'] ?? null) === CompanyCashTransaction::CATEGORY_SPENDING;

        if ($isSpending) {
            $fields['category'] = CompanyCashTransaction::CATEGORY_SPENDING;
            $fields['type'] = 'debit';
        } elseif (array_key_exists('category', $fields)) {
            // Turned back into a plain entry: no longer on anyone's behalf.
            $fields['category'] = null;
            $fields['employee_id'] = null;
        }

        return $fields;
    }

    /**
     * Total spending per employee in the given rows, largest first.
     *
     * @return list<array{employee_id: int|null, name: string, total: float, count: int}>
     */
    private function spendingByEmployee($rows): array
    {
        return $rows->filter(fn ($t) => $t->isSpending())
            ->groupBy('employee_id')
            ->map(fn ($group, $employeeId) => [
                'employee_id' => $employeeId === '' ? null : (int) $employeeId,
                'name' => $group->first()->employee?->user?->name ?? 'Unknown',
                'total' => (float) $group->sum(fn ($t) => (float) $t->amount),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Employees a spending entry can be on behalf of (for the dropdown),
     * readable with the cash book permission alone.
     */
    public function employeeOptions()
    {
        try {
            $employees = EmployeeProfile::with('user:id,name,is_active')
                ->get(['id', 'user_id'])
                ->filter(fn ($employee) => $employee->user && $employee->user->is_active !== false)
                ->map(fn ($employee) => ['id' => $employee->id, 'name' => $employee->user->name])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            return ResponseHelper::jsonResponse(true, 'Employees Retrieved Successfully', $employees, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    // Optional proof of transaction: a photo/scan or PDF of the receipt.
    private const PROOF_RULE = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    private const PROOF_ATTRIBUTES = ['proof' => 'Proof of transaction', 'employee_id' => 'On behalf of'];

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
