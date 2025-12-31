<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\CompanyFinanceStoreRequest;
use App\Http\Requests\CompanyFinanceStoreUpdateRequest;
use App\Http\Resources\CompanyFinanceResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\CompanyFinanceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CompanyFinanceController extends Controller implements HasMiddleware
{
    private CompanyFinanceRepositoryInterface $companyFinanceRepository;

    public function __construct(CompanyFinanceRepositoryInterface $companyFinanceRepository)
    {
        $this->companyFinanceRepository = $companyFinanceRepository;
    }

   public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['company-finance-list|company-finance-create|company-finance-edit|company-finance-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['company-finance-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['company-finance-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['company-finance-delete']), only: ['destroy']),
        ];
    }



    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $finances = $this->companyFinanceRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Company Finances Retrieved Successfully', CompanyFinanceResource::collection($finances), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $finances = $this->companyFinanceRepository->getAllPaginated(
                $request->search ?? null,
                $request->row_per_page
            );

            return ResponseHelper::jsonResponse(true, 'Company Finances Retrieved Successfully', PaginateResource::make($finances, CompanyFinanceResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CompanyFinanceStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $finance = $this->companyFinanceRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Company Finance Created Successfully', new CompanyFinanceResource($finance), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $finance = $this->companyFinanceRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Company Finance Retrieved Successfully', new CompanyFinanceResource($finance), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company Finance Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CompanyFinanceStoreUpdateRequest $request, int $id)
    {
        $validated = $request->validated();

        try {
            $finance = $this->companyFinanceRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Company Finance Updated Successfully', new CompanyFinanceResource($finance), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company Finance Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $this->companyFinanceRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Company Finance Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company Finance Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}