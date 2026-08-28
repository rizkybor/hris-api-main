<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProjectCashTransactionResource;
use App\Models\Project;
use App\Models\ProjectCashTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectCashTransactionController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-expense-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['project-expense-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['project-expense-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['project-expense-delete']), only: ['destroy']),
        ];
    }

    /**
     * The project's cash ledger, oldest first, with a running balance
     * computed against the project's budget as the opening balance --
     * open to anyone who can view the project (see project-expense-list).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        try {
            $project = Project::findOrFail($validated['project_id']);

            $transactions = ProjectCashTransaction::with('creator')
                ->where('project_id', $project->id)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $openingBalance = (float) $project->budget;
            $runningBalance = $openingBalance;
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            // Indonesian "buku kas" convention: Debit = uang masuk (e.g. an
            // added feature bumping the project's funding), Kredit = uang
            // keluar/pemakaian (spending) -- opposite of a general-ledger
            // debit/credit, but this is a project cash book, not a GL.
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

            return ResponseHelper::jsonResponse(true, 'Project Cash Transactions Retrieved Successfully', [
                // Oldest first, matching how the running balance was
                // computed -- reversing this without recomputing balances
                // would desync the displayed order from the ledger math.
                'items' => ProjectCashTransactionResource::collection($transactions),
                'opening_balance' => $openingBalance,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $runningBalance,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'type' => ['required', 'string', 'in:debit,credit'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $project = Project::findOrFail($validated['project_id']);

            if (! $this->canManage($project, $request->user())) {
                return ResponseHelper::jsonResponse(false, 'Only the Project Leader can record cash transactions for this project.', null, 403);
            }

            $transaction = ProjectCashTransaction::create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Recorded Successfully', new ProjectCashTransactionResource($transaction->load('creator')), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
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
            $transaction = ProjectCashTransaction::with('project')->findOrFail($id);

            if (! $this->canManage($transaction->project, $request->user())) {
                return ResponseHelper::jsonResponse(false, 'Only the Project Leader can edit this transaction.', null, 403);
            }

            $transaction->update($validated);

            return ResponseHelper::jsonResponse(true, 'Cash Transaction Updated Successfully', new ProjectCashTransactionResource($transaction->load('creator')), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Cash Transaction Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $transaction = ProjectCashTransaction::with('project')->findOrFail($id);

            if (! $this->canManage($transaction->project, $request->user())) {
                return ResponseHelper::jsonResponse(false, 'Only the Project Leader can delete this transaction.', null, 403);
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
     * Recording/editing/deleting a project's cash transactions is
     * restricted to that project's own Project Leader, except manager/
     * superadmin who may manage any project's ledger -- same convention as
     * ProjectTaskCommentController's leader-or-manager comment deletion.
     */
    private function canManage(Project $project, User $user): bool
    {
        $employeeId = $user->employeeProfile?->id;

        return ($employeeId && $project->isProjectLeader($employeeId))
            || $user->hasRole('manager')
            || $user->hasRole('superadmin');
    }
}
