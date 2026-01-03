<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\CompanyFinanceStoreRequest;
use App\Http\Requests\CompanyFinanceStoreUpdateRequest;
use App\Http\Resources\CompanyFinanceResource;
use App\Interfaces\CompanyFinanceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

use App\Services\OperationalCostStatistic\OperationalCostStatisticService;

class CompanyFinanceController extends Controller implements HasMiddleware
{
    private CompanyFinanceRepositoryInterface $companyFinanceRepository;
    protected OperationalCostStatisticService $statisticService;

    public function __construct(
        CompanyFinanceRepositoryInterface $companyFinanceRepository,
        OperationalCostStatisticService $statisticService
    ) {
        $this->companyFinanceRepository = $companyFinanceRepository;
        $this->statisticService = $statisticService;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['company-finance-menu|company-finance-create|company-finance-edit|company-finance-delete|company-finance-statistic']), only: ['index', 'show', 'getStatistic']),
            new Middleware(PermissionMiddleware::using(['company-finance-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['company-finance-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['company-finance-delete']), only: ['destroy']),
        ];
    }


    /**
     * Get aggregated operational cost statistics
     */
    public function getStatistic(Request $request)
    {
        try {
            // Ambil statistik dari service
            $data = $this->statisticService->getStatistic($request->search ?? null);

            // Ambil total saldo company langsung dari repository
            $companyBalanceData = $this->companyFinanceRepository->getStatistic($request->search ?? null);

            // Tambahkan saldo company ke response statistik
            $data['company_balance'] = number_format($companyBalanceData['summary']['total_saldo_company'], 2, '.', '');

            return ResponseHelper::jsonResponse(
                true,
                'Operational cost statistic loaded successfully',
                $data,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Internal Server Error: ' . $e->getMessage(),
                null,
                500
            );
        }
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
