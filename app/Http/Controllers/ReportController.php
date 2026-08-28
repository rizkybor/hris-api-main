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
use App\Helpers\ResponseHelper;
use App\Interfaces\ReportRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ReportController extends Controller implements HasMiddleware
{
    private ReportRepositoryInterface $reportRepository;

    public function __construct(ReportRepositoryInterface $reportRepository)
    {
        $this->reportRepository = $reportRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['report-menu|report-view']), only: ['attendance', 'payroll', 'employee', 'finance', 'pph21', 'ppn', 'project', 'pph23', 'projectExpense']),
            new Middleware(PermissionMiddleware::using(['report-export']), only: ['export']),
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
                (int) ($request->page ?? 1),
                (int) ($request->row_per_page ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'Project Cash Ledger Report Retrieved Successfully', $data, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
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
                'project_expense' => new ProjectExpenseReportExport($startDate, $endDate),
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
