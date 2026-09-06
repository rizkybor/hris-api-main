<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceReportExport;
use App\Exports\EmployeeReportExport;
use App\Exports\FinanceReportExport;
use App\Exports\PayrollReportExport;
use App\Exports\Pph21ReportExport;
use App\Exports\Pph23ReportExport;
use App\Exports\PpnReportExport;
use App\Exports\ProjectExpenseReportExport;
use App\Exports\ProjectReportExport;
use App\Exports\SubscriptionReportExport;
use App\Helpers\ResponseHelper;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Interfaces\PayrollRepositoryInterface;
use App\Interfaces\ProjectRepositoryInterface;
use App\Interfaces\ReportRepositoryInterface;
use App\Models\Attendance;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\ProjectCashTransaction;
use App\Models\Subscription;
use App\Services\CompanyCashLedgerSyncService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

class ReportController extends Controller implements HasMiddleware
{
    // Only report types backed by exactly one real, deletable record (not
    // Finance -- three tables merged into fake rows; not PPh 21 or Staff
    // Raport -- both purely computed from Payroll/Attendance, no table of
    // their own). Maps to [Model class, date column used for range delete].
    private const DELETABLE_TYPES = [
        'attendance' => [Attendance::class, 'date'],
        'payroll' => [Payroll::class, 'salary_month'],
        'employee' => [EmployeeProfile::class, 'created_at'],
        'ppn' => [Invoice::class, 'date'],
        'pph23' => [PaymentReceipt::class, 'date'],
        'project' => [Project::class, 'start_date'],
        'project_expense' => [ProjectCashTransaction::class, 'transaction_date'],
        'subscription' => [Subscription::class, 'start_date'],
    ];

    public function __construct(
        private ReportRepositoryInterface $reportRepository,
        private EmployeeProfileRepositoryInterface $employeeProfileRepository,
        private PayrollRepositoryInterface $payrollRepository,
        private ProjectRepositoryInterface $projectRepository,
        private CompanyCashLedgerSyncService $companyCashSync,
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['report-menu|report-view']), only: ['attendance', 'payroll', 'employee', 'finance', 'pph21', 'ppn', 'project', 'pph23', 'projectExpense', 'subscription']),
            new Middleware(PermissionMiddleware::using(['report-export']), only: ['export']),
            new Middleware(PermissionMiddleware::using(['staff-raport-menu|staff-raport-list']), only: ['staffRaport', 'staffRaportDetail']),
            new Middleware(PermissionMiddleware::using(['staff-raport-export']), only: ['staffRaportPdf']),
            // Deleting from a report deletes the underlying real record
            // (not just "removes it from the report"), so this is
            // deliberately gated tighter than the rest of Reports -- by
            // role, not by the regular permission system, restricted to
            // Superadmin and Manager only.
            new Middleware(RoleMiddleware::using('superadmin|manager'), only: ['deleteRow', 'deleteByRange']),
        ];
    }

    public function attendance(Request $request)
    {
        try {
            $data = $this->reportRepository->getAttendanceReport(
                $request->start_date,
                $request->end_date,
                $request->employee_id,
                $request->status,
                (int) ($request->page ?? 1),
                (int) ($request->row_per_page ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'Attendance Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function payroll(Request $request)
    {
        try {
            $data = $this->reportRepository->getPayrollReport(
                $request->start_date,
                $request->end_date,
                (int) ($request->page ?? 1),
                (int) ($request->row_per_page ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'Payroll Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function employee(Request $request)
    {
        try {
            $data = $this->reportRepository->getEmployeeReport($request->team_id, $request->status);

            return ResponseHelper::jsonResponse(true, 'Employee Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function finance(Request $request)
    {
        try {
            $data = $this->reportRepository->getFinanceReport($request->start_date, $request->end_date);

            return ResponseHelper::jsonResponse(true, 'Finance Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function pph21(Request $request)
    {
        try {
            $data = $this->reportRepository->getPph21Report($request->start_date, $request->end_date);

            return ResponseHelper::jsonResponse(true, 'PPh 21 Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function ppn(Request $request)
    {
        try {
            $data = $this->reportRepository->getPpnReport($request->start_date, $request->end_date);

            return ResponseHelper::jsonResponse(true, 'PPN Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function pph23(Request $request)
    {
        try {
            $data = $this->reportRepository->getPph23Report($request->start_date, $request->end_date);

            return ResponseHelper::jsonResponse(true, 'PPh 23 Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function subscription(Request $request)
    {
        try {
            $data = $this->reportRepository->getSubscriptionReport($request->status);

            return ResponseHelper::jsonResponse(true, 'Subscription Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function project(Request $request)
    {
        try {
            $data = $this->reportRepository->getProjectReport(
                $request->start_date,
                $request->end_date,
                $request->status,
                (int) ($request->page ?? 1),
                (int) ($request->row_per_page ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'Project Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function projectExpense(Request $request)
    {
        try {
            $data = $this->reportRepository->getProjectExpenseReport(
                $request->start_date,
                $request->end_date,
                $request->project_id ? (int) $request->project_id : null,
                (int) ($request->page ?? 1),
                (int) ($request->row_per_page ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'Project Cash Ledger Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function staffRaport(Request $request)
    {
        try {
            $data = $this->reportRepository->getStaffRaportList(
                $request->search,
                $request->employment_type,
                $request->start_date,
                $request->end_date,
                (int) ($request->page ?? 1),
                (int) ($request->row_per_page ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'Staff Raport Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function staffRaportDetail(Request $request, string $employeeId)
    {
        try {
            $data = $this->reportRepository->getStaffRaportDetail((int) $employeeId, $request->start_date, $request->end_date);

            return ResponseHelper::jsonResponse(true, 'Staff Raport Detail Retrieved Successfully', $data, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * PDF download with its own period picker (week/month/all), separate
     * from whatever date range is currently applied on the list -- per
     * spec, downloading a raport is a deliberate "give me this employee's
     * week/month/all-time summary" action, not tied to the list's filters.
     */
    public function staffRaportPdf(Request $request, string $employeeId)
    {
        $period = $request->query('period', 'month');

        try {
            $employee = EmployeeProfile::with('jobInformation')->findOrFail($employeeId);

            [$startDate, $endDate, $periodLabel] = match ($period) {
                'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString(), 'This Week'],
                'all' => [
                    $employee->jobInformation?->start_date?->toDateString() ?? '2000-01-01',
                    now()->toDateString(),
                    'All Time',
                ],
                default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), 'This Month'],
            };

            $data = $this->reportRepository->getStaffRaportDetail((int) $employeeId, $startDate, $endDate);
            $data['period_label'] = $periodLabel;
            $data['generated_at'] = now();

            $pdf = Pdf::loadView('pdf.staff-raport', $data)->setPaper('a4');

            $filename = str_replace(' ', '-', $data['employee']['name'] ?? 'staff-raport').'-'.$period.'.pdf';

            return $pdf->download($filename);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Delete a single row's underlying record. Reuses each resource's own
     * safety-checked delete path (e.g. a paid Payroll can't be deleted, the
     * Super Admin's own Employee record can't be deleted) rather than a
     * raw model delete, so this behaves exactly like deleting the same
     * record from its own dedicated page would.
     */
    public function deleteRow(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(self::DELETABLE_TYPES)),
            'id' => 'required',
        ]);

        try {
            $this->deleteEntityByTypeAndId($validated['type'], $validated['id']);

            return ResponseHelper::jsonResponse(true, 'Record Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Record Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        }
    }

    /**
     * Bulk-delete every row of a type whose date falls within the given
     * range (inclusive). Deletes row by row through the same safety-checked
     * path as deleteRow() -- a row that can't legally be deleted (e.g. a
     * paid Payroll) is skipped and reported back rather than aborting the
     * whole range.
     */
    public function deleteByRange(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(self::DELETABLE_TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        [$modelClass, $dateColumn] = self::DELETABLE_TYPES[$validated['type']];

        try {
            $ids = $modelClass::query()
                ->whereBetween($dateColumn, [$validated['start_date'], $validated['end_date']])
                ->pluck('id');

            $deleted = 0;
            $skipped = [];

            foreach ($ids as $id) {
                try {
                    $this->deleteEntityByTypeAndId($validated['type'], $id);
                    $deleted++;
                } catch (\Throwable $e) {
                    $skipped[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }

            return ResponseHelper::jsonResponse(true, "{$deleted} record(s) deleted".(count($skipped) ? ', '.count($skipped).' skipped' : ''), [
                'deleted' => $deleted,
                'skipped' => $skipped,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function deleteEntityByTypeAndId(string $type, string|int $id): void
    {
        match ($type) {
            'attendance' => Attendance::findOrFail($id)->delete(),
            'payroll' => $this->payrollRepository->deletePayroll((string) $id),
            'employee' => $this->deleteEmployeeSafely((string) $id),
            'ppn' => Invoice::findOrFail($id)->delete(),
            'pph23' => PaymentReceipt::findOrFail($id)->delete(),
            'project' => $this->projectRepository->delete((string) $id),
            'project_expense' => $this->deleteProjectCashTransaction((string) $id),
            'subscription' => Subscription::findOrFail($id)->delete(),
            default => throw new \InvalidArgumentException('Unsupported report type for deletion.'),
        };
    }

    private function deleteEmployeeSafely(string $id): void
    {
        $employee = $this->employeeProfileRepository->getById($id);

        if ($employee->user?->isProtected()) {
            throw new \Exception('The Super Admin account cannot be deleted.');
        }

        $this->employeeProfileRepository->delete($id);
    }

    private function deleteProjectCashTransaction(string $id): void
    {
        $transaction = ProjectCashTransaction::findOrFail($id);
        $this->companyCashSync->remove($transaction);
        $transaction->delete();
    }

    public function export(Request $request)
    {
        $type = $request->query('type', 'attendance');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $filename = "Report_".ucfirst($type)."_".now()->format('Ymd_His').'.xlsx';

        try {
            $export = match ($type) {
                'attendance' => new AttendanceReportExport($startDate, $endDate, $request->query('employee_id'), $request->query('status')),
                'payroll' => new PayrollReportExport($startDate, $endDate),
                'employee' => new EmployeeReportExport($request->query('team_id'), $request->query('status')),
                'finance' => new FinanceReportExport($startDate, $endDate),
                'pph21' => new Pph21ReportExport($startDate, $endDate),
                'ppn' => new PpnReportExport($startDate, $endDate),
                'project' => new ProjectReportExport($startDate, $endDate, $request->query('status')),
                'pph23' => new Pph23ReportExport($startDate, $endDate),
                'project_expense' => new ProjectExpenseReportExport($startDate, $endDate, $request->query('project_id') ? (int) $request->query('project_id') : null),
                'subscription' => new SubscriptionReportExport($request->query('status')),
                default => null,
            };

            if (! $export) {
                return ResponseHelper::jsonResponse(false, 'Invalid report type', null, 422);
            }

            return Excel::download($export, $filename);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
