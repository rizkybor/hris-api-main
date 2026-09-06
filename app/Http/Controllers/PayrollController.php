<?php

namespace App\Http\Controllers;

use App\Exports\PayrollExport;
use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PayrollDetailResource;
use App\Http\Resources\PayrollResource;
use App\Interfaces\PayrollRepositoryInterface;
use App\Jobs\GeneratePayrollJob;
use App\Jobs\GenerateThrPayrollJob;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

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
            new Middleware(PermissionMiddleware::using(['payroll-list']), only: ['index', 'getAllPaginated', 'show', 'getDetails', 'getPositions', 'exportExcel']),
            new Middleware(PermissionMiddleware::using(['payroll-create']), only: ['generate', 'generateThr', 'checkPeriod']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['updateDetail']),
            new Middleware(PermissionMiddleware::using(['payroll-process']), only: ['markAsPaid']),
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['getStatistics', 'getPayrollStatistics']),
            new Middleware(PermissionMiddleware::using(['payroll-delete']), only: ['destroy']),
            new Middleware(RoleMiddleware::using('superadmin|manager|finance'), only: ['destroyDetail']),
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
            'search' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        try {
            $perPage = $validated['per_page'] ?? 50;
            $details = $this->payrollRepository->getPayrollDetailsPaginated(
                $id,
                $perPage,
                $validated['search'] ?? null,
                $validated['position'] ?? null
            );

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
     * Distinct job positions among employees in this payroll (for the filter dropdown).
     */
    public function getPositions(string $id)
    {
        try {
            $positions = $this->payrollRepository->getPayrollPositions($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Positions Retrieved Successfully', $positions, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Lets the frontend show a confirm dialog before generating, saying
     * whether this is a brand-new period or one that already has data
     * (and would need to be regenerated, replacing it).
     */
    public function checkPeriod(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
            'type' => 'required|in:monthly,thr',
        ]);

        $month = Carbon::parse($validated['salary_month'])->startOfMonth();

        $existing = Payroll::where('salary_month', $month->format('Y-m-d'))
            ->where('type', $validated['type'])
            ->first();

        return ResponseHelper::jsonResponse(true, 'OK', [
            'exists' => (bool) $existing,
            'id' => $existing?->id,
            'status' => $existing?->status,
            'employee_count' => $existing?->payrollDetails()->count(),
            'can_regenerate' => (bool) Auth::user()?->hasAnyRole(['superadmin', 'manager', 'finance']),
        ], 200);
    }

    /**
     * Generate payroll for a specific month (Queued for background processing)
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
            'regenerate' => 'nullable|boolean',
        ]);

        try {
            // Parse salary month
            $month = Carbon::parse($validated['salary_month'])->startOfMonth();
            $regenerate = (bool) ($validated['regenerate'] ?? false);

            // Check if payroll for this month already exists
            $existingPayroll = Payroll::where('salary_month', $month->format('Y-m-d'))
                ->where('type', 'monthly')
                ->first();

            if ($existingPayroll) {
                if (! $regenerate) {
                    return ResponseHelper::jsonResponse(
                        false,
                        'Payroll for ' . $month->format('F Y') . ' already exists',
                        null,
                        400
                    );
                }

                // Re-generating replaces the existing details -- restricted
                // to the roles trusted to override an already-processed run.
                if (! Auth::user()?->hasAnyRole(['superadmin', 'manager', 'finance'])) {
                    return ResponseHelper::jsonResponse(
                        false,
                        'You are not authorized to regenerate an existing payroll.',
                        null,
                        403
                    );
                }
            }

            // Dispatch job to queue for background processing
            GeneratePayrollJob::dispatch($validated['salary_month'], $regenerate);

            return ResponseHelper::jsonResponse(
                true,
                $regenerate
                    ? 'Payroll regeneration is being processed in the background. Please check back shortly.'
                    : 'Payroll generation is being processed in the background. Please check back shortly.',
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
     * Generate THR (Tunjangan Hari Raya) for a specific month (Queued for
     * background processing) -- a separate run from the regular monthly
     * payroll, based on tenure rather than attendance.
     */
    public function generateThr(Request $request)
    {
        $validated = $request->validate([
            'salary_month' => 'required|date_format:Y-m',
            'regenerate' => 'nullable|boolean',
        ]);

        try {
            $month = Carbon::parse($validated['salary_month'])->startOfMonth();
            $regenerate = (bool) ($validated['regenerate'] ?? false);

            $existingPayroll = Payroll::where('salary_month', $month->format('Y-m-d'))
                ->where('type', 'thr')
                ->first();

            if ($existingPayroll) {
                if (! $regenerate) {
                    return ResponseHelper::jsonResponse(
                        false,
                        'THR for '.$month->format('F Y').' already exists',
                        null,
                        400
                    );
                }

                if (! Auth::user()?->hasAnyRole(['superadmin', 'manager', 'finance'])) {
                    return ResponseHelper::jsonResponse(
                        false,
                        'You are not authorized to regenerate an existing THR payroll.',
                        null,
                        403
                    );
                }
            }

            GenerateThrPayrollJob::dispatch($validated['salary_month'], $regenerate);

            return ResponseHelper::jsonResponse(
                true,
                $regenerate
                    ? 'THR regeneration is being processed in the background. Please check back shortly.'
                    : 'THR generation is being processed in the background. Please check back shortly.',
                [
                    'salary_month' => $month->format('F Y'),
                    'status' => 'processing',
                ],
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update payroll detail -- lets Superadmin/HR/Finance manually override
     * one employee's computed figures when their actual situation doesn't
     * match the automatic generation formula (e.g. an ad-hoc bonus,
     * deduction, or correction), rather than only being able to accept
     * whatever the bulk generate produced.
     */
    public function updateDetail(Request $request, string $id)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'gross_salary' => 'nullable|numeric|min:0',
            'bpjs_kesehatan_employee' => 'nullable|numeric|min:0',
            'bpjs_jht_employee' => 'nullable|numeric|min:0',
            'bpjs_jp_employee' => 'nullable|numeric|min:0',
            'bpjs_kesehatan_company' => 'nullable|numeric|min:0',
            'bpjs_jht_company' => 'nullable|numeric|min:0',
            'bpjs_jp_company' => 'nullable|numeric|min:0',
            'bpjs_jkk_company' => 'nullable|numeric|min:0',
            'bpjs_jkm_company' => 'nullable|numeric|min:0',
            'pph21' => 'nullable|numeric|min:0',
            'total_deduction' => 'nullable|numeric|min:0',
            'final_salary' => 'nullable|numeric|min:0',
            // Some staff (common at a startup) are paid a cut of a project's
            // budget instead of a fixed figure -- 'project_percentage' mode
            // derives gross/final salary from source_project_id's budget.
            'payment_mode' => 'nullable|in:manual,project_percentage',
            'source_project_id' => 'nullable|integer|exists:projects,id|required_if:payment_mode,project_percentage',
            'project_percentage' => 'nullable|numeric|min:0|max:100|required_if:payment_mode,project_percentage',
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
     * Delete a payroll run. Blocked once paid, unless the caller is
     * Superadmin/Manager/Finance (see PayrollRepository::deletePayroll()).
     */
    public function destroy(string $id)
    {
        try {
            $this->payrollRepository->deletePayroll($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete a single employee's payroll detail row -- Superadmin/Manager/
     * Finance only (see middleware()).
     */
    public function destroyDetail(string $id)
    {
        try {
            $this->payrollRepository->deletePayrollDetail($id);

            return ResponseHelper::jsonResponse(true, 'Payroll Detail Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payroll Detail Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
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
