<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ProjectTask;
use App\Models\StaffTaskAssignee;

/**
 * Derives a data-driven performance score for one employee over a date
 * range -- 50% attendance rate, 50% task completion rate -- converted to a
 * 1-5 star rating. Deliberately separate from PerformanceReview, which is a
 * manager-submitted qualitative review with its own free-text rating; this
 * is a computed metric pulled straight from attendance and task records.
 */
class StaffPerformanceCalculator
{
    private const ATTENDANCE_WEIGHT = 0.5;

    private const TASK_WEIGHT = 0.5;

    public function calculate(int $employeeId, string $startDate, string $endDate): array
    {
        $attendance = $this->attendanceBreakdown($employeeId, $startDate, $endDate);
        $tasks = $this->taskBreakdown($employeeId, $startDate, $endDate);

        $attendanceRate = $attendance['total'] > 0
            ? round((($attendance['present'] + $attendance['late']) / $attendance['total']) * 100, 1)
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

    /**
     * @return array{total: int, present: int, late: int, absent: int, sick_leave: int}
     */
    /**
     * Saturday/Sunday are excluded outright (DAYOFWEEK: 1=Sunday,
     * 7=Saturday) -- they aren't scheduled work days, so they never enter
     * the attendance rate at all, regardless of status. A weekend
     * clock-in is tagged 'overtime' rather than present/late anyway (see
     * AttendanceRepository::checkIn()), but this filter is what actually
     * keeps the rate honest even if that tagging ever changes.
     */
    private function attendanceBreakdown(int $employeeId, string $startDate, string $endDate): array
    {
        $counts = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereRaw('DAYOFWEEK(date) NOT IN (1, 7)')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $overtimeDays = (int) Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'overtime')
            ->count();

        return [
            'total' => (int) $counts->sum(),
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'sick_leave' => (int) ($counts['sick_leave'] ?? 0),
            'overtime_days' => $overtimeDays,
        ];
    }

    /**
     * "Due in period" (not "created in period") is the denominator, so a
     * task assigned earlier but due this week still counts here -- and
     * "completed" is read off task/assignee status, not a completed_at
     * column (project_tasks has none; staff_task_assignees tracks status
     * only, timestamped via status_updated_at).
     *
     * @return array{total: int, done: int, project_tasks: int, staff_tasks: int}
     */
    private function taskBreakdown(int $employeeId, string $startDate, string $endDate): array
    {
        $projectTasksQuery = ProjectTask::where('assignee_id', $employeeId)
            ->whereBetween('due_date', [$startDate, $endDate]);
        $projectTasksTotal = (clone $projectTasksQuery)->count();
        $projectTasksDone = (clone $projectTasksQuery)->where('status', 'done')->count();

        $staffTasksQuery = StaffTaskAssignee::where('employee_id', $employeeId)
            ->whereHas('task', fn ($q) => $q->whereBetween('due_date', [$startDate, $endDate]));
        $staffTasksTotal = (clone $staffTasksQuery)->count();
        $staffTasksDone = (clone $staffTasksQuery)->where('status', 'done')->count();

        return [
            'total' => $projectTasksTotal + $staffTasksTotal,
            'done' => $projectTasksDone + $staffTasksDone,
            'project_tasks' => $projectTasksTotal,
            'staff_tasks' => $staffTasksTotal,
        ];
    }
}
