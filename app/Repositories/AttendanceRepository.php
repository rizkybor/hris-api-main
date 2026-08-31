<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\AttendanceDto;
use App\Interfaces\AttendanceRepositoryInterface;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    private const MIN_WORK_MINUTES_BEFORE_CHECK_OUT = 8 * 60;

    public function getAll(
        ?string $search,
        ?string $date,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Attendance::with(['employee.user', 'employee.jobInformation.team'])
            ->where(function ($query) use ($search, $date) {
                if ($search) {
                    $query->search($search);
                }

                if ($date) {
                    // Use direct comparison instead of whereDate for better performance
                    $query->whereBetween('date', [
                        $date.' 00:00:00',
                        $date.' 23:59:59',
                    ]);
                }
            })
            ->orderBy('created_at', 'desc');

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
        int $rowPerPage,
        ?string $status = null
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null, // date
            null, // limit
            false
        );

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): Attendance {
        return Attendance::with(['employee.user'])
            ->findOrFail($id);
    }

    public function getMyAttendances(): Collection
    {
        $employeeId = Auth::user()->employeeProfile?->id;

        if (! $employeeId) {
            return new Collection();
        }

        return Attendance::with(['employee.user'])
            ->where('employee_id', $employeeId)
            ->whereDate('date', '>=', now()->subDays(6)->startOfDay())
            ->whereDate('date', '<=', now()->endOfDay())
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getMyAttendanceStatistics()
    {
        // Accounts with no employee profile (e.g. Super Admin) have no
        // attendance to report -- zeroed stats, not a query against NULL.
        $employeeId = Auth::user()->employeeProfile?->id;

        if (! $employeeId) {
            return [
                'total_days' => now()->day,
                'present_days' => 0,
                'sick_days' => 0,
                'absent_days' => 0,
                'average_hours' => 0,
            ];
        }

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $totalDays = now()->day; // Days elapsed in current month

        // Single optimized query instead of 3 separate queries
        $stats = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw("
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'sick' THEN 1 END) as sick_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                AVG(CASE WHEN check_in IS NOT NULL AND check_out IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, check_in, check_out)
                END) as avg_minutes
            ")
            ->first();

        return [
            'total_days' => $totalDays,
            'present_days' => (int) $stats->present_days,
            'sick_days' => (int) $stats->sick_days,
            'absent_days' => (int) $stats->absent_days,
            'average_hours' => $stats->avg_minutes ? round($stats->avg_minutes / 60, 1) : 0,
        ];
    }

    public function getLastAttendanceByEmployee(): ?Attendance
    {
        $employeeId = Auth::user()->employeeProfile?->id;

        if (! $employeeId) {
            return null;
        }

        return Attendance::with(['employee.user'])
            ->where('employee_id', $employeeId)
            ->where('date', now()->format('Y-m-d'))
            ->first();
    }

    /**
     * Saturday/Sunday clock-ins are blocked by default (Sat/Sun aren't
     * scheduled work days) -- Superadmin/Manager can flip this open in
     * Settings, in which case a weekend clock-in is recorded as overtime
     * ("lembur") rather than a normal present/late day, so it never
     * pollutes the regular attendance rate. On a normal work day, clocking
     * in after 09:00 WIB is marked late, with how many minutes late
     * carried on the record for display.
     */
    public function checkIn(array $data): Attendance
    {
        return DB::transaction(function () use ($data) {
            $employeeId = Auth::user()->employeeProfile?->id;

            if (! $employeeId) {
                throw new \Exception('This account has no employee profile, so it cannot clock in.');
            }

            $existingAttendance = Attendance::where('employee_id', $employeeId)
                ->where('date', now()->format('Y-m-d'))
                ->first();

            if ($existingAttendance) {
                throw new \Exception('Employee sudah check in hari ini');
            }

            $now = Carbon::now('Asia/Jakarta');
            $isWeekend = $now->isWeekend();

            if ($isWeekend && ! AttendanceSetting::current()->allow_weekend_check_in) {
                throw new \Exception('Clock in tidak tersedia pada hari Sabtu/Minggu. Hubungi Superadmin/Manager jika Anda perlu masuk lembur.');
            }

            $status = 'present';
            $lateMinutes = null;

            if ($isWeekend) {
                $status = 'overtime';
            } else {
                $lateThreshold = $now->copy()->setTime(9, 0, 0);
                if ($now->greaterThan($lateThreshold)) {
                    $status = 'late';
                    $lateMinutes = $lateThreshold->diffInMinutes($now);
                }
            }

            $attendanceData = array_merge($data, [
                'date' => Carbon::now(),
                'check_in' => Carbon::now(),
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]);

            $attendanceDto = AttendanceDto::fromArray($attendanceData);

            return Attendance::create($attendanceDto->toArray());
        });
    }

    public function checkOut(array $data): Attendance
    {
        return DB::transaction(function () use ($data) {
            $employeeId = Auth::user()->employeeProfile?->id;

            if (! $employeeId) {
                throw new \Exception('This account has no employee profile, so it cannot clock out.');
            }

            $attendance = Attendance::where('employee_id', $employeeId)
                ->where('date', now()->format('Y-m-d'))
                ->whereNull('check_out')
                ->first();

            if (! $attendance) {
                throw new \Exception('Tidak ada data check in hari ini');
            }

            $checkOutTime = Carbon::now();

            $minutesWorked = (int) floor(Carbon::parse($attendance->check_in)->diffInMinutes($checkOutTime));
            if ($minutesWorked < self::MIN_WORK_MINUTES_BEFORE_CHECK_OUT) {
                $remainingMinutes = self::MIN_WORK_MINUTES_BEFORE_CHECK_OUT - $minutesWorked;
                $hours = intdiv($remainingMinutes, 60);
                $minutes = $remainingMinutes % 60;
                throw new \Exception("Belum bisa check out. Sisa {$hours} jam {$minutes} menit lagi untuk mencapai 8 jam kerja.");
            }

            $updateData = array_merge($data, [
                'check_out' => $checkOutTime,
            ]);

            $attendanceDto = AttendanceDto::fromArrayForUpdate($updateData, $attendance);
            $attendance->update($attendanceDto->toArray());

            return $attendance->load(['employee.user']);
        });
    }

    public function getStatistics()
    {
        $cacheKey = CacheConstants::CACHE_KEY_ATTENDANCE_STATISTICS.now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            $today = now()->format('Y-m-d');
            $yesterday = now()->subDay()->format('Y-m-d');
            $lastWeekStart = now()->subWeek()->startOfWeek()->format('Y-m-d');
            $lastWeekEnd = now()->subWeek()->endOfWeek()->format('Y-m-d');

            // Single optimized query for attendance stats (today & yesterday)
            $attendanceStats = DB::table('attendances')
                ->selectRaw("
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'present'
                        THEN 1
                    END) as present_today,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'present'
                        THEN 1
                    END) as present_yesterday,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'absent'
                        THEN 1
                    END) as absent_today,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'absent'
                        THEN 1
                    END) as absent_yesterday,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND TIME(check_in) > '09:00:00'
                        THEN 1
                    END) as late_today,
                    COUNT(CASE
                        WHEN DATE(date) BETWEEN ? AND ?
                        AND status = 'present'
                        THEN 1
                    END) as last_week_present
                ", [
                    $today,
                    $yesterday,
                    $today,
                    $yesterday,
                    $today,
                    $lastWeekStart,
                    $lastWeekEnd,
                ])
                ->first();

            // Remote workers today
            $remoteToday = DB::table('attendances')
                ->join('employee_profiles', 'attendances.employee_id', '=', 'employee_profiles.id')
                ->join('job_information', 'employee_profiles.id', '=', 'job_information.employee_id')
                ->where('attendances.date', '>=', $today.' 00:00:00')
                ->where('attendances.date', '<=', $today.' 23:59:59')
                ->where('job_information.work_location', 'remote')
                ->count();

            // Leave requests stats
            $leaveStats = DB::table('leave_requests')
                ->selectRaw("
                    COUNT(CASE
                        WHEN status = 'approved'
                        AND start_date <= ?
                        AND end_date >= ?
                        THEN 1
                    END) as on_leave_today,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests
                ", [$today, $today])
                ->first();

            // Get cached employee count
            $totalEmployees = cache()->remember(CacheConstants::CACHE_KEY_EMPLOYEE_TOTAL_COUNT, CacheConstants::ONE_HOUR, function () {
                return DB::table('employee_profiles')->whereNull('deleted_at')->count();
            });

            // Calculate rates
            $presentToday = (int) $attendanceStats->present_today;
            $presentYesterday = (int) $attendanceStats->present_yesterday;
            $absentToday = (int) $attendanceStats->absent_today;
            $absentYesterday = (int) $attendanceStats->absent_yesterday;
            $lastWeekPresent = (int) $attendanceStats->last_week_present;

            $attendanceRate = $totalEmployees > 0
                ? round(($presentToday / $totalEmployees) * 100, 1)
                : 0;

            $lastWeekDays = 5;
            $lastWeekRate = $totalEmployees > 0 && $lastWeekDays > 0
                ? round(($lastWeekPresent / ($totalEmployees * $lastWeekDays)) * 100, 1)
                : 0;

            return [
                'present_today' => $presentToday,
                'present_change' => $presentToday - $presentYesterday,
                'absent_today' => $absentToday,
                'absent_change' => $absentToday - $absentYesterday,
                'late_today' => (int) $attendanceStats->late_today,
                'on_leave_today' => (int) $leaveStats->on_leave_today,
                'remote_today' => $remoteToday,
                'attendance_rate' => $attendanceRate,
                'rate_change' => round($attendanceRate - $lastWeekRate, 1),
                'pending_requests' => (int) $leaveStats->pending_requests,
            ];
        });
    }

    /**
     * Clear attendance statistics cache
     */
    public function clearAttendanceCache(): void
    {
        cache()->forget(CacheConstants::CACHE_KEY_ATTENDANCE_STATISTICS.now()->format('Y-m-d-H'));
    }
}
