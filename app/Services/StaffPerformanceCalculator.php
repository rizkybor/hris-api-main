<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\JobInformation;
use App\Models\LeaveRequest;
use App\Models\ProjectTask;
use App\Models\StaffTaskAssignee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Derives a data-driven performance score for one employee over a date
 * range -- 50% attendance, 50% task completion -- converted to a 1-5 star
 * rating. Deliberately separate from PerformanceReview, which is a
 * manager-submitted qualitative review; this is computed straight from
 * attendance and task records.
 *
 * Attendance is scored per working day (Mon-Fri, not a holiday, from the
 * employee's join date up to today, WIB):
 *   on time / remote / weekday overtime  100
 *   late <= 15 min                         90
 *   late 16-60 min                         75
 *   late > 60 min                          50
 *   half day                               50
 *   absent, or no check-in at all           0
 *   sick / approved leave              not counted
 * so a day nobody clocked in on counts against the rate instead of being
 * invisible, and being late costs something.
 */
class StaffPerformanceCalculator
{
    private const TZ = 'Asia/Jakarta';

    private const ATTENDANCE_WEIGHT = 0.5;

    private const TASK_WEIGHT = 0.5;

    /** [max late minutes, points] -- first tier that fits wins. */
    public const LATE_TIERS = [[15, 90], [60, 75], [PHP_INT_MAX, 50]];

    private const FULL_DAY = ['present', 'remote', 'overtime'];

    private const EXCUSED = ['sick_leave', 'annual_leave'];

    public function calculate(int $employeeId, string $startDate, string $endDate): array
    {
        $attendance = $this->attendanceBreakdown($employeeId, $startDate, $endDate);
        $tasks = $this->taskBreakdown($employeeId, $startDate, $endDate);

        $attendanceRate = $attendance['total'] > 0
            ? round($attendance['points'] / $attendance['total'], 1)
            : null;

        $taskRate = $tasks['total'] > 0
            ? round(($tasks['done'] / $tasks['total']) * 100, 1)
            : null;

        $overallScore = $this->weightedScore($attendanceRate, $taskRate);
        $stars = $overallScore !== null ? (int) max(0, min(5, round(($overallScore / 100) * 5))) : 0;

        return [
            'attendance' => $attendance,
            'attendance_rate' => $attendanceRate,
            'tasks' => $tasks,
            'task_completion_rate' => $taskRate,
            'overall_score' => $overallScore,
            'stars' => $stars,
        ];
    }

    public static function latePoints(?int $minutes): int
    {
        foreach (self::LATE_TIERS as [$max, $points]) {
            if (($minutes ?? 0) <= $max) {
                return $points;
            }
        }

        return 50;
    }

    /**
     * No data on one side falls back to the other side alone, rather than
     * dragging the score down for something that simply never happened
     * (e.g. an employee with zero tasks due in the period isn't "50%
     * failing" -- they just have nothing to measure there).
     */
    private function weightedScore(?float $attendanceRate, ?float $taskRate): ?float
    {
        if ($attendanceRate === null && $taskRate === null) {
            return null;
        }

        if ($attendanceRate === null) {
            return $taskRate;
        }

        if ($taskRate === null) {
            return $attendanceRate;
        }

        return round($attendanceRate * self::ATTENDANCE_WEIGHT + $taskRate * self::TASK_WEIGHT, 1);
    }

    /** The part of the range that can be judged: from joining, up to today. */
    private function window(int $employeeId, string $startDate, string $endDate): array
    {
        $today = Carbon::now(self::TZ)->toDateString();
        $joined = JobInformation::where('employee_id', $employeeId)->value('start_date');
        $joined = $joined ? Carbon::parse($joined)->toDateString() : null;

        $from = $joined && $joined > $startDate ? $joined : $startDate;
        $to = min($endDate, $today);

        return [$from, $to, $today];
    }

    private function attendanceBreakdown(int $employeeId, string $startDate, string $endDate): array
    {
        [$from, $to, $today] = $this->window($employeeId, $startDate, $endDate);

        $records = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString());

        $holidays = Holiday::between($startDate, $endDate);
        $onLeave = $this->approvedLeaveDays($employeeId, $startDate, $endDate);

        $out = [
            'working_days' => 0, 'total' => 0, 'points' => 0,
            'present' => 0, 'remote' => 0, 'late' => 0, 'late_minutes' => 0,
            'late_tiers' => ['minor' => 0, 'moderate' => 0, 'severe' => 0],
            'half_day' => 0, 'absent' => 0, 'no_record' => 0,
            'sick_leave' => 0, 'leave' => 0, 'holidays' => 0,
            'overtime_days' => $records->where('status', 'overtime')->count(),
        ];

        if ($from > $to) {
            return $out;
        }

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            if ($day->isWeekend()) {
                continue;
            }
            if (isset($holidays[$date])) {
                $out['holidays']++;

                continue;
            }

            $out['working_days']++;
            $record = $records->get($date);
            $status = $record?->status;

            // Excused: sick/leave on the record, or approved leave with no
            // proper check-in that day.
            if (in_array($status, self::EXCUSED, true)) {
                $status === 'sick_leave' ? $out['sick_leave']++ : $out['leave']++;

                continue;
            }
            if (isset($onLeave[$date]) && ! in_array($status, [...self::FULL_DAY, 'late', 'half_day'], true)) {
                $onLeave[$date] === 'sick_leave' ? $out['sick_leave']++ : $out['leave']++;

                continue;
            }

            if (! $record) {
                // Today isn't over yet -- no check-in so far isn't a miss.
                if ($date === $today) {
                    $out['working_days']--;

                    continue;
                }
                $out['no_record']++;
                $out['total']++;

                continue;
            }

            $out['total']++;
            if (in_array($status, self::FULL_DAY, true)) {
                $status === 'remote' ? $out['remote']++ : $out['present']++;
                $out['points'] += 100;
            } elseif ($status === 'late') {
                $minutes = (int) ($record->late_minutes ?? 0);
                $points = self::latePoints($minutes);
                $out['late']++;
                $out['late_minutes'] += $minutes;
                $out['late_tiers'][$points === 90 ? 'minor' : ($points === 75 ? 'moderate' : 'severe')]++;
                $out['points'] += $points;
            } elseif ($status === 'half_day') {
                $out['half_day']++;
                $out['points'] += 50;
            } else {
                $out['absent']++;
            }
        }

        return $out;
    }

    /** "Y-m-d" => leave type for every day covered by an approved leave. */
    private function approvedLeaveDays(int $employeeId, string $startDate, string $endDate): array
    {
        $days = [];
        LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->get()
            ->each(function ($leave) use (&$days) {
                $type = $leave->leave_type?->value ?? (string) $leave->leave_type;
                foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $day) {
                    $days[$day->toDateString()] = $type;
                }
            });

        return $days;
    }

    /**
     * Tasks due in the period count once their due date has arrived (a task
     * due next week isn't failed yet), or earlier if already done. Cancelled
     * project tasks are left out. "Done" comes from task/assignee status;
     * for staff tasks, status_updated_at also tells whether it was finished
     * after the due date.
     */
    private function taskBreakdown(int $employeeId, string $startDate, string $endDate): array
    {
        $cutoff = min($endDate, Carbon::now(self::TZ)->toDateString());

        $project = ProjectTask::where('assignee_id', $employeeId)
            ->whereBetween('due_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'status', 'due_date']);
        $projectCounted = $project->filter(fn ($t) => $t->status === 'done' || Carbon::parse($t->due_date)->toDateString() <= $cutoff);

        $staff = StaffTaskAssignee::where('employee_id', $employeeId)
            ->whereHas('task', fn ($q) => $q->whereBetween('due_date', [$startDate, $endDate]))
            ->with('task:id,due_date')
            ->get();
        $staffCounted = $staff->filter(fn ($a) => $a->status === 'done' || $a->task->due_date->toDateString() <= $cutoff);
        $staffDone = $staffCounted->where('status', 'done');
        $staffDoneLate = $staffDone->filter(fn ($a) => $a->status_updated_at
            && $a->status_updated_at->copy()->setTimezone(self::TZ)->toDateString() > $a->task->due_date->toDateString());

        return [
            'total' => $projectCounted->count() + $staffCounted->count(),
            'done' => $projectCounted->where('status', 'done')->count() + $staffDone->count(),
            'project_tasks' => $projectCounted->count(),
            'project_done' => $projectCounted->where('status', 'done')->count(),
            'staff_tasks' => $staffCounted->count(),
            'staff_done' => $staffDone->count(),
            'staff_done_late' => $staffDoneLate->count(),
            'upcoming' => ($project->count() - $projectCounted->count()) + ($staff->count() - $staffCounted->count()),
        ];
    }
}
