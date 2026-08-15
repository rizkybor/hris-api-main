<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PayslipResource;
use App\Models\PayrollDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    /**
     * List the authenticated employee's paid payslips.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'row_per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
            'search' => 'nullable|string',
            'year' => 'nullable|integer',
        ]);

        try {
            $employeeId = $request->user()->employeeProfile?->id;

            $query = PayrollDetail::with('payroll')
                ->where('employee_id', $employeeId)
                ->whereHas('payroll', function ($q) use ($validated) {
                    $q->where('status', 'paid');
                    if (! empty($validated['year'])) {
                        $q->whereYear('salary_month', $validated['year']);
                    }
                });

            if (! empty($validated['search'])) {
                $query->whereHas('payroll', function ($q) use ($validated) {
                    $q->whereRaw("DATE_FORMAT(salary_month, '%M %Y') LIKE ?", ['%'.$validated['search'].'%']);
                });
            }

            $payslips = $query->latest('created_at')
                ->paginate($validated['row_per_page'] ?? 12, ['*'], 'page', $validated['page'] ?? 1);

            return ResponseHelper::jsonResponse(true, 'Payslips Retrieved Successfully', PaginateResource::make($payslips, PayslipResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Show a single payslip owned by the authenticated employee.
     */
    public function show(Request $request, string $id)
    {
        try {
            $detail = $this->findOwnedPayslip($request, $id);

            return ResponseHelper::jsonResponse(true, 'Payslip Retrieved Successfully', new PayslipResource($detail), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payslip Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Download a payslip as PDF.
     */
    public function download(Request $request, string $id)
    {
        $detail = $this->findOwnedPayslip($request, $id);

        $basicSalary = (float) $detail->original_salary;
        $netSalary = (float) $detail->final_salary;

        $pdf = Pdf::loadView('pdf.payslip', [
            'period' => $detail->payroll->salary_month,
            'paymentDate' => $detail->payroll->payment_date,
            'employeeName' => $detail->employee?->user?->name,
            'department' => $detail->employee?->jobInformation?->team?->name,
            'basicSalary' => $basicSalary,
            'grossSalary' => $basicSalary,
            'totalDeductions' => $basicSalary - $netSalary,
            'netSalary' => $netSalary,
            'notes' => $detail->notes,
        ])->setPaper('a4');

        $filename = 'Payslip-'.$detail->payroll->salary_month->format('F-Y').'.pdf';

        return $pdf->stream($filename);
    }

    private function findOwnedPayslip(Request $request, string $id): PayrollDetail
    {
        $employeeId = $request->user()->employeeProfile?->id;

        return PayrollDetail::with(['payroll', 'employee.user', 'employee.jobInformation.team'])
            ->where('employee_id', $employeeId)
            ->whereHas('payroll', fn ($q) => $q->where('status', 'paid'))
            ->findOrFail($id);
    }
}
