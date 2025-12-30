<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\CredentialAccountStoreRequest;
use App\Http\Requests\CredentialAccountUpdateRequest;
use App\Http\Resources\CredentialAccountResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\CredentialAccountRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CredentialAccountController extends Controller implements HasMiddleware
{
    private CredentialAccountRepositoryInterface $credentialAccountRepository;

    public function __construct(CredentialAccountRepositoryInterface $credentialAccountRepository)
    {
        $this->credentialAccountRepository = $credentialAccountRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['credential-account-list|credential-account-create|credential-account-edit|credential-account-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['credential-account-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['credential-account-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['credential-account-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->credentialAccountRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Credential Accounts Retrieved Successfully', CredentialAccountResource::collection($accounts), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $accounts = $this->credentialAccountRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Credential Accounts Retrieved Successfully', PaginateResource::make($accounts, CredentialAccountResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CredentialAccountStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $account = $this->credentialAccountRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Credential Account Created Successfully', new CredentialAccountResource($account), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $account = $this->credentialAccountRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Credential Account Retrieved Successfully', new CredentialAccountResource($account), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Credential Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CredentialAccountUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $account = $this->credentialAccountRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Credential Account Updated Successfully', new CredentialAccountResource($account), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Credential Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->credentialAccountRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Credential Account Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Credential Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}

