<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\EmployeeProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PayrollRepository implements PayrollRepositoryInterface
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Payroll::with(['payrollDetails'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('payrollDetails.employee', function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%');
                    })
                        ->orWhere('code', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('salary_month', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(string $id): Payroll
    {
        return Payroll::withCount('payrollDetails')
            ->findOrFail($id);
    }

    public function getPayrollDetailsPaginated(string $payrollId, int $perPage = 50): LengthAwarePaginator
    {
        // Verify payroll exists
        $payroll = Payroll::findOrFail($payrollId);

        // Get paginated details with optimized eager loading
        return PayrollDetail::with([
            'employee.user',
            'employee.jobInformation.team',
            'employee.bankInformation',
        ])
            ->where('payroll_id', $payrollId)
            ->orderBy('final_salary', 'desc') // Highest salary first
            ->paginate($perPage);
    }

    public function generatePayroll(string $salaryMonth): Payroll
    {
        return DB::transaction(function () use ($salaryMonth) {
            $month = Carbon::parse($salaryMonth)->startOfMonth();

            $existingPayroll = Payroll::where('salary_month', $month->format('Y-m-d'))->first();

            if ($existingPayroll) {
                throw new \Exception('Payroll untuk bulan ' . $month->format('F Y') . ' sudah dibuat');
            }

            $payroll = Payroll::create([
                'salary_month' => $month->format('Y-m-d'),
                'status' => 'processing',
            ]);

            $employeeIdsWithAttendance = Attendance::whereBetween('date', [
                $month->copy()->startOfMonth()->format('Y-m-d H:i:s'),
                $month->copy()->endOfMonth()->format('Y-m-d H:i:s'),
            ])
                ->distinct()
                ->pluck('employee_id')
                ->toArray();

            if (empty($employeeIdsWithAttendance)) {
                throw new \Exception('Tidak ada data absensi untuk bulan ini');
            }

            $activeEmployees = EmployeeProfile::with(['jobInformation', 'user'])
                ->whereIn('id', $employeeIdsWithAttendance)
                ->whereHas('jobInformation', function ($query) {
                    $query->where('status', 'active');
                })
                ->get();

            if ($activeEmployees->isEmpty()) {
                throw new \Exception('Tidak ada karyawan aktif dengan data absensi');
            }

            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();
            $workingDays = $this->calculateWorkingDays($startOfMonth, $endOfMonth);

            $employeeIds = $activeEmployees->pluck('id')->toArray();

            $attendanceStats = DB::table('attendances')
                ->select(
                    'employee_id',
                    DB::raw("COUNT(CASE WHEN status = 'present' THEN 1 END) as attended_days"),
                    DB::raw("COUNT(CASE WHEN status = 'sick' THEN 1 END) as sick_days"),
                    DB::raw("COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days"),
                    DB::raw("COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days"),
                    DB::raw("COUNT(CASE WHEN status = 'permission' THEN 1 END) as permission_days")
                )
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [
                    $startOfMonth->format('Y-m-d H:i:s'),
                    $endOfMonth->format('Y-m-d H:i:s'),
                ])
                ->groupBy('employee_id')
                ->get()
                ->keyBy('employee_id');

            $payrollDetails = [];

            foreach ($activeEmployees as $employee) {
                $jobInfo = $employee->jobInformation;
                $originalSalary = $jobInfo->monthly_salary ?? 0;

                $stats = $attendanceStats->get($employee->id);
                $attendedDays = $stats->attended_days ?? 0;
                $lateDays = $stats->late_days ?? 0;
                $sickDays = $stats->sick_days ?? 0;
                $absentDays = $stats->absent_days ?? 0;
                $permissionDays = $stats->permission_days ?? 0;

                $dailySalary = $workingDays > 0 ? $originalSalary / $workingDays : 0;

                $deduction = $absentDays * $dailySalary;
                $finalSalary = $originalSalary - $deduction;

                $payrollDetails[] = [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'original_salary' => $originalSalary,
                    'final_salary' => max(0, $finalSalary),
                    'attended_days' => $attendedDays + $lateDays,
                    'sick_days' => $sickDays,
                    'absent_days' => $absentDays,
                    'notes' => "Hari kerja: {$workingDays} | Hadir: {$attendedDays} | Terlambat: {$lateDays} | Sakit: {$sickDays} | Izin: {$permissionDays} | Alpha: {$absentDays} | Potongan: Rp " . number_format($deduction, 0, ',', '.'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($payrollDetails)) {
                foreach (array_chunk($payrollDetails, CacheConstants::PAYROLL_BULK_INSERT_CHUNK_SIZE) as $chunk) {
                    DB::table('payroll_details')->insert($chunk);
                }
            }

            $payroll->update(['status' => 'pending']);

            return $payroll->load([
                'payrollDetails.employee.user',
                'payrollDetails.employee.jobInformation.team',
                'payrollDetails.employee.bankInformation',
            ]);
        });
    }

    public function updatePayrollDetail(string $id, array $data): PayrollDetail
    {
        return DB::transaction(function () use ($id, $data) {
            $payrollDetail = PayrollDetail::findOrFail($id);

            if ($payrollDetail->payroll->status === 'paid') {
                throw new \Exception('Tidak dapat mengubah payroll yang sudah dibayar');
            }

            $updateData = [];
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }
            if (isset($data['final_salary'])) {
                $updateData['final_salary'] = $data['final_salary'];
            }

            $payrollDetail->update($updateData);

            return $payrollDetail->load([
                'employee.user',
                'employee.jobInformation.team',
                'payroll',
            ]);
        });
    }

    public function markAsPaid(string $payrollId, string $paymentDate): Payroll
    {
        return DB::transaction(function () use ($payrollId, $paymentDate) {
            $payroll = Payroll::findOrFail($payrollId);

            if ($payroll->status === 'paid') {
                throw new \Exception('Payroll sudah dibayar');
            }

            $payroll->update([
                'status' => 'paid',
                'payment_date' => Carbon::parse($paymentDate)->format('Y-m-d'),
            ]);

            DB::afterCommit(function () use ($payroll) {
                $this->emailService->sendPayrollPaidNotifications($payroll->id);
            });

            return $payroll->loadCount('payrollDetails');
        });
    }

    public function getStatistics()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Current month payroll
        $currentPayroll = Payroll::where('salary_month', $currentMonth->format('Y-m-d'))->first();
        $lastPayroll = Payroll::where('salary_month', $lastMonth->format('Y-m-d'))->first();

        $totalEmployeesCurrentMonth = $currentPayroll
            ? $currentPayroll->payrollDetails()->count()
            : 0;

        $totalSalaryCurrentMonth = $currentPayroll
            ? $currentPayroll->payrollDetails()->sum('final_salary')
            : 0;

        $totalSalaryLastMonth = $lastPayroll
            ? $lastPayroll->payrollDetails()->sum('final_salary')
            : 0;

        $paidPayrolls = Payroll::where('status', 'paid')
            ->whereYear('salary_month', now()->year)
            ->count();

        $pendingPayrolls = Payroll::where('status', 'pending')
            ->count();

        // Calculate average salary
        $averageSalary = $totalEmployeesCurrentMonth > 0
            ? $totalSalaryCurrentMonth / $totalEmployeesCurrentMonth
            : 0;

        // Calculate total deductions (difference between original and final salary)
        $totalDeductions = $currentPayroll
            ? $currentPayroll->payrollDetails()->selectRaw('SUM(original_salary - final_salary) as total_deductions')->value('total_deductions')
            : 0;

        return [
            'total_payroll' => $totalEmployeesCurrentMonth,
            'pending_review' => $pendingPayrolls,
            'finalized' => $paidPayrolls,
            'total_amount' => round($totalSalaryCurrentMonth, 2),
            'average_salary' => round($averageSalary, 2),
            'deductions' => round($totalDeductions ?? 0, 2),
            // Backward compatibility
            'total_employees' => $totalEmployeesCurrentMonth,
            'total_salary_current_month' => round($totalSalaryCurrentMonth, 2),
            'total_salary_last_month' => round($totalSalaryLastMonth, 2),
            'salary_change' => $totalSalaryLastMonth > 0
                ? round((($totalSalaryCurrentMonth - $totalSalaryLastMonth) / $totalSalaryLastMonth) * 100, 1)
                : 0,
            'paid_payrolls' => $paidPayrolls,
            'pending_payrolls' => $pendingPayrolls,
        ];
    }

    public function getPayrollStatistics(string $payrollId)
    {
        // Cache key for payroll-specific statistics
        $cacheKey = CacheConstants::CACHE_KEY_PAYROLL_STATISTICS . $payrollId . '_' . now()->format('Y-m-d-H');

        // Cache for 1 hour
        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($payrollId) {
            $payroll = Payroll::findOrFail($payrollId);

            // Get all statistics in optimized queries
            $detailStats = PayrollDetail::where('payroll_id', $payrollId)
                ->selectRaw('
                    COUNT(*) as total_employees,
                    SUM(original_salary) as total_original_salary,
                    SUM(final_salary) as total_final_salary,
                    SUM(original_salary - final_salary) as total_deductions,
                    AVG(final_salary) as average_salary,
                    MAX(final_salary) as highest_salary,
                    MIN(final_salary) as lowest_salary,
                    SUM(attended_days) as total_attended_days,
                    SUM(sick_days) as total_sick_days,
                    SUM(absent_days) as total_absent_days
                ')
                ->first();

            return [
                'payroll_id' => $payroll->id,
                'salary_month' => $payroll->salary_month,
                'status' => $payroll->status,
                'payment_date' => $payroll->payment_date,
                'processed_date' => $payroll->created_at->format('Y-m-d'),
                'total_employees' => $detailStats->total_employees ?? 0,
                'total_amount' => round($detailStats->total_final_salary ?? 0, 2),
                'total_original_salary' => round($detailStats->total_original_salary ?? 0, 2),
                'total_deductions' => round($detailStats->total_deductions ?? 0, 2),
                'average_salary' => round($detailStats->average_salary ?? 0, 2),
                'highest_salary' => round($detailStats->highest_salary ?? 0, 2),
                'lowest_salary' => round($detailStats->lowest_salary ?? 0, 2),
                'total_attended_days' => $detailStats->total_attended_days ?? 0,
                'total_sick_days' => $detailStats->total_sick_days ?? 0,
                'total_absent_days' => $detailStats->total_absent_days ?? 0,
            ];
        });
    }

    /**
     * Calculate working days (exclude weekends: Saturday & Sunday)
     */
    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Check if not weekend (Saturday = 6, Sunday = 0)
            if (! $currentDate->isWeekend()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }

        return $workingDays;
    }
}
