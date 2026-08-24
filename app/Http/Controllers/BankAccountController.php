<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class BankAccountController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['invoice-menu|invoice-list|invoice-create']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['invoice-create', 'invoice-edit']), only: ['store', 'update']),
            new Middleware(PermissionMiddleware::using(['invoice-delete']), only: ['destroy']),
        ];
    }

    public function index()
    {
        $accounts = BankAccount::orderBy('bank_name')->get();

        return ResponseHelper::jsonResponse(true, 'Bank Accounts Retrieved Successfully', $accounts, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_code' => ['nullable', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50'],
        ]);

        $account = BankAccount::create($validated);

        return ResponseHelper::jsonResponse(true, 'Bank Account Created Successfully', $account, 201);
    }

    public function update(Request $request, string $id)
    {
        $account = BankAccount::findOrFail($id);

        $validated = $request->validate([
            'bank_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bank_code' => ['nullable', 'string', 'max:50'],
            'account_number' => ['sometimes', 'required', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50'],
        ]);

        $account->update($validated);

        return ResponseHelper::jsonResponse(true, 'Bank Account Updated Successfully', $account, 200);
    }

    public function destroy(string $id)
    {
        $account = BankAccount::findOrFail($id);
        $account->delete();

        return ResponseHelper::jsonResponse(true, 'Bank Account Deleted Successfully', null, 200);
    }
}
