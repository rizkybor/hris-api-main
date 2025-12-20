<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\Interfaces\DashboardRepositoryInterface;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function getStatistics()
    {
        $cacheKey = CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            $currentMonth = now()->month;
            $currentYear = now()->year;
            $yesterday = Carbon::yesterday()->toDateString();
            $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
            $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

            // Single query for employee and team stats
            $employeeTeamStats = DB::table('employee_profiles')
                ->crossJoin('teams')
                ->selectRaw('
                    COUNT(DISTINCT employee_profiles.id) as total_employees,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(employee_profiles.created_at) = ?
                        AND YEAR(employee_profiles.created_at) = ?
                        THEN employee_profiles.id
                    END) as employees_this_month,
                    COUNT(DISTINCT teams.id) as total_teams,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(teams.created_at) = ?
                        AND YEAR(teams.created_at) = ?
                        THEN teams.id
                    END) as new_teams_this_month
                ', [
                    $currentMonth,
                    $currentYear,
                    $currentMonth,
                    $currentYear,
                ])
                ->first();

            // Single query for attendance stats
            $attendanceStats = DB::table('attendances')
                ->selectRaw('
                    COUNT(CASE
                        WHEN MONTH(created_at) = ?
                        AND YEAR(created_at) = ?
                        THEN 1
                    END) as total_this_month,
                    COUNT(CASE
                        WHEN created_at BETWEEN ? AND ?
                        THEN 1
                    END) as total_last_week
                ', [
                    $currentMonth,
                    $currentYear,
                    $lastWeekStart,
                    $lastWeekEnd,
                ])
                ->first();

            // Calculate attendance rates
            $totalWorkDays = now()->day;
            $totalEmployees = (int) $employeeTeamStats->total_employees;
            $totalExpectedAttendances = $totalEmployees * $totalWorkDays;
            $totalActualAttendances = (int) $attendanceStats->total_this_month;

            $attendanceRate = $totalExpectedAttendances > 0
                ? round(($totalActualAttendances / $totalExpectedAttendances) * 100, 1)
                : 0;

            $lastWeekWorkDays = 5;
            $lastWeekExpectedAttendances = $totalEmployees * $lastWeekWorkDays;
            $lastWeekActualAttendances = (int) $attendanceStats->total_last_week;

            $lastWeekAttendanceRate = $lastWeekExpectedAttendances > 0
                ? round(($lastWeekActualAttendances / $lastWeekExpectedAttendances) * 100, 1)
                : 0;

            $attendanceRateChange = round($attendanceRate - $lastWeekAttendanceRate, 1);

            // Single query for task stats
            $taskStats = DB::table('project_tasks')
                ->selectRaw("
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as total_completed,
                    COUNT(CASE
                        WHEN status = 'completed'
                        AND DATE(updated_at) = ?
                        THEN 1
                    END) as completed_yesterday
                ", [$yesterday])
                ->first();

            $tasksCompleted = (int) $taskStats->total_completed;
            $tasksCompletedYesterday = (int) $taskStats->completed_yesterday;
            $tasksChange = $tasksCompleted - $tasksCompletedYesterday;

            // Single query for project stats
            $projectStats = DB::table('projects')
                ->selectRaw("
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
                    COUNT(CASE
                        WHEN status = 'active'
                        AND MONTH(created_at) = ?
                        AND YEAR(created_at) = ?
                        THEN 1
                    END) as new_projects_this_month
                ", [
                    $currentMonth,
                    $currentYear,
                ])
                ->first();

            return [
                'employees' => [
                    'total' => (int) $employeeTeamStats->total_employees,
                    'added_this_month' => (int) $employeeTeamStats->employees_this_month,
                ],
                'teams' => [
                    'total' => (int) $employeeTeamStats->total_teams,
                    'new_teams' => (int) $employeeTeamStats->new_teams_this_month,
                ],
                'attendance' => [
                    'rate' => $attendanceRate,
                    'change' => $attendanceRateChange,
                ],
                'tasks' => [
                    'completed' => $tasksCompleted,
                    'change' => $tasksChange,
                ],
                'projects' => [
                    'active' => (int) $projectStats->active_projects,
                    'new_projects' => (int) $projectStats->new_projects_this_month,
                ],
            ];
        });
    }

    public function getEmployeeStatistics(int $employeeId)
    {
        $cacheKey = CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS . 'employee_' . $employeeId . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($employeeId) {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            $yesterday = Carbon::yesterday()->toDateString();

            $attendanceStats = DB::table('attendances')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->selectRaw("
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'sick_leave' THEN 1 END) as sick_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
                ")
                ->first();

            $totalDays = now()->day;
            $presentDays = (int) ($attendanceStats->present_days ?? 0);
            $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

            $taskStats = DB::table('project_tasks')
                ->where('assignee_id', $employeeId)
                ->selectRaw("
                    COUNT(CASE WHEN status = 'done' THEN 1 END) as total_done,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN status = 'todo' THEN 1 END) as todo,
                    COUNT(CASE WHEN status = 'review' THEN 1 END) as review,
                    COUNT(CASE
                        WHEN status = 'done'
                        AND DATE(updated_at) = ?
                        THEN 1
                    END) as done_yesterday
                ", [$yesterday])
                ->first();

            $assignedProjects = DB::table('project_teams')
                ->join('team_members', 'project_teams.team_id', '=', 'team_members.team_id')
                ->join('projects', 'projects.id', '=', 'project_teams.project_id')
                ->where('team_members.employee_id', $employeeId)
                ->whereNull('team_members.left_at')
                ->where('projects.status', 'active')
                ->selectRaw('COUNT(DISTINCT projects.id) as assigned_count')
                ->first();

            $ledProjects = DB::table('projects')
                ->where('project_leader_id', $employeeId)
                ->where('status', 'active')
                ->selectRaw('COUNT(*) as led_count')
                ->first();

            return [
                'attendance' => [
                    'rate' => $attendanceRate,
                    'present_days' => $presentDays,
                    'absent_days' => (int) ($attendanceStats->absent_days ?? 0),
                    'sick_days' => (int) ($attendanceStats->sick_days ?? 0),
                    'late_days' => (int) ($attendanceStats->late_days ?? 0),
                ],
                'tasks' => [
                    'done' => (int) ($taskStats->total_done ?? 0),
                    'done_yesterday' => (int) ($taskStats->done_yesterday ?? 0),
                    'in_progress' => (int) ($taskStats->in_progress ?? 0),
                    'todo' => (int) ($taskStats->todo ?? 0),
                    'review' => (int) ($taskStats->review ?? 0),
                ],
                'projects' => [
                    'assigned_active' => (int) ($assignedProjects->assigned_count ?? 0),
                    'led_active' => (int) ($ledProjects->led_count ?? 0),
                ],
            ];
        });
    }

    /**
     * Clear dashboard statistics cache
     */
    public function clearDashboardCache(): void
    {
        cache()->forget(CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS . now()->format('Y-m-d-H'));
    }
}
