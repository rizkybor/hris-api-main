<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\EmployeeProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\Project;
use App\Services\EmailService;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollRepository implements PayrollRepositoryInterface
{
    /**
     * Roles exempt from the attendance requirement in generatePayroll() --
     * they're still paid in full even with zero attendance records for
     * the month.
     */
    private const ATTENDANCE_EXEMPT_ROLES = ['manager', 'finance', 'operational_director'];

    protected EmailService $emailService;

    protected PayrollCalculationService $payrollCalculationService;

    public function __construct(EmailService $emailService, PayrollCalculationService $payrollCalculationService)
    {
        $this->emailService = $emailService;
        $this->payrollCalculationService = $payrollCalculationService;
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

    /**
     * Distinct job titles among employees included in this payroll, for the
     * "filter by position" dropdown.
     */
    public function getPayrollPositions(string $payrollId): array
    {
        Payroll::findOrFail($payrollId);

        return PayrollDetail::where('payroll_id', $payrollId)
            ->join('employee_profiles', 'employee_profiles.id', '=', 'payroll_details.employee_id')
            ->join('job_information', 'job_information.employee_id', '=', 'employee_profiles.id')
            ->whereNotNull('job_information.job_title')
            ->distinct()
            ->orderBy('job_information.job_title')
            ->pluck('job_information.job_title')
            ->values()
            ->toArray();
    }

    public function getPayrollDetailsPaginated(string $payrollId, int $perPage = 50, ?string $search = null, ?string $position = null): LengthAwarePaginator
    {
        // Verify payroll exists
        $payroll = Payroll::findOrFail($payrollId);

        // Get paginated details with optimized eager loading
        return PayrollDetail::with([
            'employee.user',
            'employee.jobInformation.team',
            'employee.bankInformation',
            'sourceProject',
        ])
            ->where('payroll_id', $payrollId)
            ->when($search, function ($query) use ($search) {
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('code', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($position, function ($query) use ($position) {
                $query->whereHas('employee.jobInformation', function ($q) use ($position) {
                    $q->where('job_title', $position);
                });
            })
            ->orderBy('final_salary', 'desc') // Highest salary first
            ->paginate($perPage);
    }

    public function generatePayroll(string $salaryMonth, bool $regenerate = false): Payroll
    {
        return DB::transaction(function () use ($salaryMonth, $regenerate) {
            $month = Carbon::parse($salaryMonth)->startOfMonth();

            $existingPayroll = Payroll::where('salary_month', $month->format('Y-m-d'))
                ->where('type', 'monthly')
                ->first();

            // A previously-deleted Payroll for this period is soft-deleted,
            // so it's invisible to the query above but still occupies the
            // (salary_month, type) unique index -- clear it out first or the
            // create() below collides with it.
            if (! $existingPayroll) {
                Payroll::onlyTrashed()
                    ->where('salary_month', $month->format('Y-m-d'))
                    ->where('type', 'monthly')
                    ->get()
                    ->each(function (Payroll $trashed) {
                        $trashed->payrollDetails()->forceDelete();
                        $trashed->forceDelete();
                    });
            }

            if ($existingPayroll && ! $regenerate) {
                throw new \Exception('Payroll untuk bulan ' . $month->format('F Y') . ' sudah dibuat');
            }

            if ($existingPayroll) {
                // Re-generate: replace this payroll's existing details with
                // freshly computed ones instead of creating a second Payroll
                // row for the same month -- restricted server-side (see
                // PayrollController::generate()) to Superadmin/Manager/Finance.
                // forceDelete (not soft-delete) because the fresh insert below
                // reuses the same (payroll_id, employee_id) pairs, which the
                // unique index would otherwise collide with a soft-deleted row.
                $payroll = $existingPayroll;
                $payroll->payrollDetails()->forceDelete();
                $payroll->update(['status' => 'processing']);
            } else {
                $payroll = Payroll::create([
                    'salary_month' => $month->format('Y-m-d'),
                    'type' => 'monthly',
                    'status' => 'processing',
                ]);
            }

            $employeeIdsWithAttendance = Attendance::whereBetween('date', [
                $month->copy()->startOfMonth()->format('Y-m-d H:i:s'),
                $month->copy()->endOfMonth()->format('Y-m-d H:i:s'),
            ])
                ->distinct()
                ->pluck('employee_id')
                ->toArray();

            // Manager/Finance/Operational Director are exempt from attendance
            // tracking (no clock-in requirement), so they're still included
            // -- with full attendance assumed -- even with zero attendance
            // records for the month. Off by default; a Superadmin/Manager
            // turns it on via Settings > Payroll (see PayrollSettingController).
            $attendanceExemptEmployeeIds = PayrollSetting::current()->attendance_exempt_roles_enabled
                ? EmployeeProfile::whereHas('user.roles', function ($query) {
                    $query->whereIn('name', self::ATTENDANCE_EXEMPT_ROLES);
                })->pluck('id')->toArray()
                : [];

            $employeeIdsToInclude = array_unique(array_merge($employeeIdsWithAttendance, $attendanceExemptEmployeeIds));

            if (empty($employeeIdsToInclude)) {
                throw new \Exception('Tidak ada data absensi untuk bulan ini');
            }

            $activeEmployees = EmployeeProfile::with(['jobInformation', 'user.roles'])
                ->whereIn('id', $employeeIdsToInclude)
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
                $ptkpStatus = $jobInfo->ptkp_status ?? 'TK/0';

                $stats = $attendanceStats->get($employee->id);

                if ($stats) {
                    $attendedDays = $stats->attended_days;
                    $lateDays = $stats->late_days;
                    $sickDays = $stats->sick_days;
                    $absentDays = $stats->absent_days;
                    $permissionDays = $stats->permission_days;
                } elseif ($employee->user?->hasAnyRole(self::ATTENDANCE_EXEMPT_ROLES)) {
                    // No attendance records at all -- exempt role, assume
                    // full attendance rather than treating it as absence.
                    $attendedDays = $workingDays;
                    $lateDays = 0;
                    $sickDays = 0;
                    $absentDays = 0;
                    $permissionDays = 0;
                } else {
                    $attendedDays = 0;
                    $lateDays = 0;
                    $sickDays = 0;
                    $absentDays = 0;
                    $permissionDays = 0;
                }

                $dailySalary = $workingDays > 0 ? $originalSalary / $workingDays : 0;

                $attendanceDeduction = $absentDays * $dailySalary;
                $grossSalary = max(0, $originalSalary - $attendanceDeduction);

                $calc = $this->payrollCalculationService->calculate((float) $grossSalary, $ptkpStatus);
                $totalDeduction = $attendanceDeduction + $calc['total_employee_deduction'];
                $finalSalary = $calc['take_home_pay'];

                $payrollDetails[] = [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'original_salary' => $originalSalary,
                    'gross_salary' => $grossSalary,
                    'final_salary' => max(0, $finalSalary),
                    'attended_days' => $attendedDays + $lateDays,
                    'sick_days' => $sickDays,
                    'absent_days' => $absentDays,
                    'bpjs_kesehatan_employee' => $calc['bpjs_kesehatan_employee'],
                    'bpjs_jht_employee' => $calc['bpjs_jht_employee'],
                    'bpjs_jp_employee' => $calc['bpjs_jp_employee'],
                    'bpjs_kesehatan_company' => $calc['bpjs_kesehatan_company'],
                    'bpjs_jht_company' => $calc['bpjs_jht_company'],
                    'bpjs_jp_company' => $calc['bpjs_jp_company'],
                    'bpjs_jkk_company' => $calc['bpjs_jkk_company'],
                    'bpjs_jkm_company' => $calc['bpjs_jkm_company'],
                    'pph21' => $calc['pph21'],
                    'total_deduction' => round($totalDeduction, 2),
                    'notes' => "Hari kerja: {$workingDays} | Hadir: {$attendedDays} | Terlambat: {$lateDays} | Sakit: {$sickDays} | Izin: {$permissionDays} | Alpha: {$absentDays} | Potongan Absensi: Rp " . number_format($attendanceDeduction, 0, ',', '.') . ' | BPJS: Rp ' . number_format($calc['bpjs_kesehatan_employee'] + $calc['bpjs_jht_employee'] + $calc['bpjs_jp_employee'], 0, ',', '.') . ' | PPh21: Rp ' . number_format($calc['pph21'], 0, ',', '.'),
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

    /**
     * THR (Tunjangan Hari Raya): a separate payroll run from the regular
     * monthly one (same salary_month is allowed since `type` distinguishes
     * them -- see the migration). Unlike generatePayroll(), eligibility is
     * driven by tenure (job_information.start_date), not attendance -- an
     * employee who has worked at least 1 full month is entitled to a
     * prorated THR regardless of that month's attendance record.
     */
    public function generateThrPayroll(string $salaryMonth, bool $regenerate = false): Payroll
    {
        return DB::transaction(function () use ($salaryMonth, $regenerate) {
            $month = Carbon::parse($salaryMonth)->startOfMonth();

            $existingPayroll = Payroll::where('salary_month', $month->format('Y-m-d'))
                ->where('type', 'thr')
                ->first();

            // See the same guard in generatePayroll() above.
            if (! $existingPayroll) {
                Payroll::onlyTrashed()
                    ->where('salary_month', $month->format('Y-m-d'))
                    ->where('type', 'thr')
                    ->get()
                    ->each(function (Payroll $trashed) {
                        $trashed->payrollDetails()->forceDelete();
                        $trashed->forceDelete();
                    });
            }

            if ($existingPayroll && ! $regenerate) {
                throw new \Exception('THR untuk bulan '.$month->format('F Y').' sudah dibuat');
            }

            if ($existingPayroll) {
                // forceDelete for the same reason as generatePayroll() above --
                // avoids colliding with the (payroll_id, employee_id) unique
                // index on the fresh insert.
                $payroll = $existingPayroll;
                $payroll->payrollDetails()->forceDelete();
                $payroll->update(['status' => 'processing']);
            } else {
                $payroll = Payroll::create([
                    'salary_month' => $month->format('Y-m-d'),
                    'type' => 'thr',
                    'status' => 'processing',
                ]);
            }

            $endOfMonth = $month->copy()->endOfMonth();

            $activeEmployees = EmployeeProfile::with(['jobInformation', 'user'])
                ->whereHas('jobInformation', function ($query) use ($endOfMonth) {
                    $query->where('status', 'active')
                        ->whereNotNull('start_date')
                        ->where('start_date', '<=', $endOfMonth->format('Y-m-d'));
                })
                ->get();

            $payrollDetails = [];

            foreach ($activeEmployees as $employee) {
                $jobInfo = $employee->jobInformation;
                $monthlySalary = (float) ($jobInfo->monthly_salary ?? 0);
                $ptkpStatus = $jobInfo->ptkp_status ?? 'TK/0';

                $monthsOfService = (int) Carbon::parse($jobInfo->start_date)->diffInMonths($endOfMonth);

                // Permenaker No. 6/2016: at least 1 full month of continuous
                // service is required to be eligible for a (prorated) THR.
                if ($monthsOfService < 1 || $monthlySalary <= 0) {
                    continue;
                }

                $calc = $this->payrollCalculationService->calculateThr($monthlySalary, $monthsOfService, $ptkpStatus);

                $payrollDetails[] = [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'original_salary' => $monthlySalary,
                    'gross_salary' => $calc['gross_thr'],
                    'final_salary' => $calc['net_thr'],
                    'attended_days' => 0,
                    'sick_days' => 0,
                    'absent_days' => 0,
                    'months_of_service' => $calc['months_of_service'],
                    'bpjs_kesehatan_employee' => 0,
                    'bpjs_jht_employee' => 0,
                    'bpjs_jp_employee' => 0,
                    'bpjs_kesehatan_company' => 0,
                    'bpjs_jht_company' => 0,
                    'bpjs_jp_company' => 0,
                    'bpjs_jkk_company' => 0,
                    'bpjs_jkm_company' => 0,
                    'pph21' => $calc['pph21'],
                    'total_deduction' => $calc['pph21'],
                    'notes' => "THR -- Masa kerja: {$calc['months_of_service']}/12 bulan | Gaji bulanan: Rp ".number_format($monthlySalary, 0, ',', '.')." | THR Kotor: Rp ".number_format($calc['gross_thr'], 0, ',', '.').' | PPh21: Rp '.number_format($calc['pph21'], 0, ',', '.'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (empty($payrollDetails)) {
                throw new \Exception('Tidak ada karyawan yang memenuhi syarat masa kerja minimal 1 bulan untuk THR bulan ini');
            }

            foreach (array_chunk($payrollDetails, CacheConstants::PAYROLL_BULK_INSERT_CHUNK_SIZE) as $chunk) {
                DB::table('payroll_details')->insert($chunk);
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

            $editableFields = [
                'notes',
                'gross_salary',
                'bpjs_kesehatan_employee',
                'bpjs_jht_employee',
                'bpjs_jp_employee',
                'bpjs_kesehatan_company',
                'bpjs_jht_company',
                'bpjs_jp_company',
                'bpjs_jkk_company',
                'bpjs_jkm_company',
                'pph21',
                'total_deduction',
                'final_salary',
                'payment_mode',
                'source_project_id',
                'project_percentage',
            ];

            $updateData = array_intersect_key($data, array_flip($editableFields));

            // Percentage-of-project pay bypasses the attendance-based salary
            // components entirely -- the project's budget cut IS the take-home
            // figure, so it's derived server-side (never trusted from the
            // client) and any BPJS/PPh21 breakdown is zeroed out since it
            // doesn't apply to this payment scheme.
            if (($data['payment_mode'] ?? null) === 'project_percentage') {
                $project = Project::findOrFail($data['source_project_id']);
                $computed = round(((float) $project->budget) * ((float) $data['project_percentage'] / 100), 2);

                $updateData['gross_salary'] = $computed;
                $updateData['final_salary'] = $computed;
                $updateData['bpjs_kesehatan_employee'] = 0;
                $updateData['bpjs_jht_employee'] = 0;
                $updateData['bpjs_jp_employee'] = 0;
                $updateData['pph21'] = 0;
                $updateData['total_deduction'] = 0;
            }

            $payrollDetail->update($updateData);

            return $payrollDetail->load([
                'employee.user',
                'employee.jobInformation.team',
                'payroll',
                'sourceProject',
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

    public function deletePayroll(string $id): void
    {
        DB::transaction(function () use ($id) {
            $payroll = Payroll::findOrFail($id);

            // A paid payroll can only be removed by Superadmin/Manager/Finance
            // -- everyone else with payroll-delete permission is still
            // blocked, same as before.
            $isPaid = $payroll->status === 'paid';
            $canForceDelete = Auth::user()?->hasAnyRole(['superadmin', 'manager', 'finance']);

            if ($isPaid && ! $canForceDelete) {
                throw new \Exception('Tidak dapat menghapus payroll yang sudah dibayar');
            }

            $payroll->payrollDetails()->delete();
            $payroll->delete();
        });
    }

    public function deletePayrollDetail(string $id): void
    {
        DB::transaction(function () use ($id) {
            $payrollDetail = PayrollDetail::findOrFail($id);
            $payrollDetail->delete();
        });
    }

    public function getStatistics()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Current month payroll (regular monthly run only -- a THR run for
        // the same month, if any, is a separate figure)
        $currentPayroll = Payroll::where('salary_month', $currentMonth->format('Y-m-d'))->where('type', 'monthly')->first();
        $lastPayroll = Payroll::where('salary_month', $lastMonth->format('Y-m-d'))->where('type', 'monthly')->first();

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
            $monthStart = Carbon::parse($payroll->salary_month)->startOfMonth();
            $workingDays = $this->calculateWorkingDays($monthStart, $monthStart->copy()->endOfMonth());

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
                    SUM(absent_days) as total_absent_days,
                    SUM(pph21) as total_pph21,
                    SUM(bpjs_kesehatan_employee + bpjs_jht_employee + bpjs_jp_employee) as total_bpjs_employee,
                    SUM(bpjs_kesehatan_company + bpjs_jht_company + bpjs_jp_company + bpjs_jkk_company + bpjs_jkm_company) as total_bpjs_company
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
                'total_pph21' => round($detailStats->total_pph21 ?? 0, 2),
                'total_bpjs_employee' => round($detailStats->total_bpjs_employee ?? 0, 2),
                'total_bpjs_company' => round($detailStats->total_bpjs_company ?? 0, 2),
                'working_days' => $workingDays,
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
