<?php

namespace App\Http\Controllers;

use App\Exports\PayrollExport;
use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PayrollDetailResource;
use App\Http\Resources\PayrollResource;
use App\Interfaces\PayrollRepositoryInterface;
use App\Jobs\GeneratePayrollJob;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollController extends Controller implements HasMiddleware
{
    private PayrollRepositoryInterface $payrollRepository;

    public function __construct(PayrollRepositoryInterface $payrollRepository)
    {
        $this->payrollRepository = $payrollRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-list']), only: ['index', 'getAllPaginated', 'show', 'getDetails', 'exportExcel']),
            new Middleware(PermissionMiddleware::using(['payroll-create']), only: ['generate']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['updateDetail']),
            new Middleware(PermissionMiddleware::using(['payroll-process']), only: ['markAsPaid']),
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['getStatistics', 'getPayrollStatistics']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $payrolls = $this->payrollRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', PayrollResource::collection($payrolls), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get all payrolls with pagination
     */
    public function getAllPaginated(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        try {
            $payrolls = $this->payrollRepository->getAllPaginated(
                $validated['search'] ?? null,
                $validated['row_per_page'] ?? 10
            );

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', PaginateResource::make($payrolls, PayrollResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource (summary only, without details)
     */
    public function show(string $id)
    {
        try {
            $payroll = $this->payrollRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Retrieved Successfully', new PayrollResource($payroll), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get payroll details with pagination (OPTIMIZED for large datasets)
     */
    public function getDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:10|max:100',
            'page' => 'nullable|integer',
        ]);

        try {
            $perPage = $validated['per_page'] ?? 50;
            $details = $this->payrollRepository->getPayrollDetailsPaginated($id, $perPage);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Details Retrieved Successfully',
                PaginateResource::make($details, PayrollDetailResource::class),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate payroll for a specific month (Queued for background processing)
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
        ]);

        try {
            // Parse salary month
            $month = Carbon::parse($validated['salary_month'])->startOfMonth();

            // Check if payroll for this month already exists
            $existingPayroll = Payroll::where('salary_month', $month->format('Y-m-d'))->first();

            if ($existingPayroll) {
                return ResponseHelper::jsonResponse(
                    false,
                    'Payroll for ' . $month->format('F Y') . ' already exists',
                    null,
                    400
                );
            }

            // Dispatch job to queue for background processing
            GeneratePayrollJob::dispatch($validated['salary_month']);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll generation is being processed in the background. Please check back shortly.',
                [
                    'salary_month' => $month->format('F Y'),
                    'status' => 'processing',
                ],
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update payroll detail (notes and final_salary)
     */
    public function updateDetail(Request $request, string $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'final_salary' => 'nullable|numeric|min:0',
        ]);

        try {
            $payrollDetail = $this->payrollRepository->updatePayrollDetail($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Payroll Detail Updated Successfully', new PayrollDetailResource($payrollDetail), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Detail Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(Request $request, string $id)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
        ]);

        try {
            $payroll = $this->payrollRepository->markAsPaid($id, $validated['payment_date']);

            return ResponseHelper::jsonResponse(true, 'Payroll Marked as Paid Successfully', new PayrollResource($payroll), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get payroll statistics
     */
    public function getStatistics()
    {
        try {
            $statistics = $this->payrollRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Payroll Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get specific payroll statistics
     */
    public function getPayrollStatistics(string $id)
    {
        try {
            $statistics = $this->payrollRepository->getPayrollStatistics($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Statistics Retrieved Successfully', $statistics, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Export payroll to Excel
     */
    public function exportExcel(string $id)
    {
        try {
            // Verify payroll exists
            $payroll = Payroll::findOrFail($id);

            // Generate filename
            $month = Carbon::parse($payroll->salary_month)->format('F_Y');
            $filename = "Payroll_{$month}.xlsx";

            // Export to Excel
            return Excel::download(new PayrollExport($id), $filename);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
