<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\TeamDto;
use App\Interfaces\TeamRepositoryInterface;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeamRepository implements TeamRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $leaderId,
        ?string $status,
        ?string $department,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Team::with([
            'leader',
            'leader.employeeProfile',
            'leader.employeeProfile.jobInformation',
        ])
            ->where(function ($query) use ($search, $leaderId, $status, $department) {
                if ($search) {
                    $query->search($search);
                }

                if ($leaderId) {
                    $query->where('team_lead_id', $leaderId);
                }

                if ($status) {
                    $query->where('status', $status);
                }

                if ($department) {
                    $query->where('department', $department);
                }
            })->withCount('members')->withCount('projects')->orderByDesc('created_at');

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
        ?int $leaderId,
        ?string $status,
        ?string $department,
        ?int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            $leaderId,
            $status,
            $department,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): Team {
        return Team::with([
            'leader',
            'leader.employeeProfile',
            'leader.employeeProfile.jobInformation',
            'members',
            'members.employee',
            'members.employee.user',
            'members.employee.jobInformation',
        ])->withCount('members')->findOrFail($id);
    }

    public function create(array $data): Team
    {
        return DB::transaction(function () use ($data) {
            $teamDto = TeamDto::fromArray($data);
            $team = Team::create($teamDto->toArray());

            if (isset($data['icon'])) {
                $iconPath = $data['icon']->store('team-icons', 'public');
                $team->update(['icon' => $iconPath]);
            }

            if ($team->team_lead_id) {
                $leader = User::with('employeeProfile')->find($team->team_lead_id);
                if ($leader && $leader->employeeProfile) {
                    TeamMember::updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'employee_id' => $leader->employeeProfile->id,
                        ],
                        [
                            'joined_at' => now(),
                            'left_at' => null,
                        ]
                    );
                }
            }

            // Clear caches
            $this->clearTeamCaches($team->id);

            return $team->load(['leader', 'members']);
        });
    }

    public function update(string $id, array $data): Team
    {
        return DB::transaction(function () use ($id, $data) {
            $team = $this->getById($id);
            $oldLeaderId = $team->team_lead_id;

            $teamDto = TeamDto::fromArrayForUpdate($data, $team);
            $team->update($teamDto->toArray());

            if (isset($data['icon'])) {
                if ($team->icon && Storage::disk('public')->exists($team->icon)) {
                    Storage::disk('public')->delete($team->icon);
                }

                $iconPath = $data['icon']->store('team-icons', 'public');
                $team->update(['icon' => $iconPath]);
            }

            if ($team->team_lead_id && $team->team_lead_id !== $oldLeaderId) {
                if ($oldLeaderId) {
                    $oldLeader = User::with('employeeProfile')->find($oldLeaderId);
                    if ($oldLeader && $oldLeader->employeeProfile) {
                        TeamMember::where('team_id', $team->id)
                            ->where('employee_id', $oldLeader->employeeProfile->id)
                            ->whereNull('left_at')
                            ->update(['left_at' => now()]);
                    }
                }

                $newLeader = User::with('employeeProfile')->find($team->team_lead_id);
                if ($newLeader && $newLeader->employeeProfile) {
                    TeamMember::updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'employee_id' => $newLeader->employeeProfile->id,
                        ],
                        [
                            'joined_at' => now(),
                            'left_at' => null,
                        ]
                    );
                }
            }

            // Clear caches
            $this->clearTeamCaches($team->id);

            return $team;
        });
    }

    public function delete(string $id): Team
    {
        return DB::transaction(function () use ($id) {
            $team = $this->getById($id);

            if ($team->icon && Storage::disk('public')->exists($team->icon)) {
                Storage::disk('public')->delete($team->icon);
            }

            // Clear caches before deleting
            $this->clearTeamCaches($team->id);

            $team->delete();

            return $team;
        });
    }

    public function getStatistics(): array
    {
        // Cache key for statistics
        $cacheKey = CacheConstants::CACHE_KEY_TEAM_STATISTICS.now()->format('Y-m-d-H');

        // Cache for 1 hour
        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            // Get all team statistics in a single optimized query
            $teamStats = Team::selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as active,
                COUNT(CASE WHEN YEAR(created_at) = ? AND MONTH(created_at) = ? THEN 1 END) as added_this_month,
                COUNT(CASE WHEN status = ? AND created_at <= ? THEN 1 END) as active_last_week
            ', [
                'active',
                now()->year,
                now()->month,
                'active',
                now()->subWeek()->endOfWeek(),
            ])->first();

            // Get all member statistics in a single optimized query
            $memberStats = TeamMember::selectRaw('
                COUNT(CASE WHEN left_at IS NULL THEN 1 END) as total,
                COUNT(CASE WHEN left_at IS NULL AND YEAR(joined_at) = ? AND MONTH(joined_at) = ? THEN 1 END) as added_this_month,
                COUNT(CASE WHEN left_at IS NULL AND joined_at < ? THEN 1 END) as last_month_total
            ', [
                now()->year,
                now()->month,
                now()->startOfMonth(),
            ])->first();

            $totalTeams = $teamStats->total;
            $totalMembers = $memberStats->total;
            $activeTeams = $teamStats->active;
            $activeTeamsLastWeek = $teamStats->active_last_week;
            $teamsThisMonth = $teamStats->added_this_month;

            // Calculate changes
            $activeTeamsChange = $activeTeams - $activeTeamsLastWeek;
            $membersChange = $totalMembers - $memberStats->last_month_total;

            // Calculate average team size
            $averageTeamSize = $totalTeams > 0 ? round($totalMembers / $totalTeams, 1) : 0;

            return [
                'total' => $totalTeams,
                'added_this_month' => $teamsThisMonth,
                'active' => $activeTeams,
                'active_change' => $activeTeamsChange,
                'members' => $totalMembers,
                'members_change' => $membersChange,
                'average_size' => $averageTeamSize,
                'new_teams' => $teamsThisMonth, // Same as added_this_month
            ];
        });
    }

    public function getTeamStatistics(string $teamId): array
    {
        // Cache key for team-specific statistics
        $cacheKey = CacheConstants::CACHE_KEY_TEAM_STATISTICS.$teamId.'_'.now()->format('Y-m-d-H');

        // Cache for 1 hour
        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($teamId) {
            $team = Team::withCount('members')->findOrFail($teamId);

            // Get project statistics in a single query
            $projectStats = Project::whereHas('teams', function ($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as in_progress
            ', ['in_progress'])->first();

            // Get task statistics in a single optimized query
            $taskStats = DB::table('project_tasks')
                ->join('projects', 'project_tasks.project_id', '=', 'projects.id')
                ->join('project_teams', 'projects.id', '=', 'project_teams.project_id')
                ->where('project_teams.team_id', $teamId)
                ->selectRaw('
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN project_tasks.status = ? THEN 1 END) as completed_tasks,
                    COUNT(CASE WHEN YEAR(project_tasks.created_at) = ? AND MONTH(project_tasks.created_at) = ? THEN 1 END) as tasks_this_month,
                    COUNT(CASE WHEN project_tasks.status = ? AND YEAR(project_tasks.updated_at) = ? AND MONTH(project_tasks.updated_at) = ? THEN 1 END) as completed_this_month
                ', [
                    'done',
                    now()->year,
                    now()->month,
                    'done',
                    now()->year,
                    now()->month,
                ])->first();

            // Calculate performance metrics
            $teamPerformance = $taskStats->total_tasks > 0
                ? round(($taskStats->completed_tasks / $taskStats->total_tasks) * 100)
                : 0;

            $completionRate = $taskStats->tasks_this_month > 0
                ? round(($taskStats->completed_this_month / $taskStats->tasks_this_month) * 100)
                : 0;

            return [
                'active_members' => $team->members_count,
                'projects_assigned' => $projectStats->total ?? 0,
                'projects_in_progress' => $projectStats->in_progress ?? 0,
                'team_performance' => $teamPerformance,
                'completion_rate' => $completionRate,
            ];
        });
    }

    public function getTeamChartData(string $teamId): array
    {
        // Cache key for chart data
        $cacheKey = CacheConstants::CACHE_KEY_TEAM_CHART_DATA.$teamId.'_'.now()->format('Y-m-d');

        // Cache for 24 hours
        return cache()->remember($cacheKey, CacheConstants::ONE_DAY, function () use ($teamId) {
            $months = [];

            // Prepare month dates
            for ($i = 3; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $key = $date->format('Y-m');
                $months[$key] = $date->format('M');
            }

            // Get all member data in a single query
            $memberStats = TeamMember::where('team_id', $teamId)
                ->whereNull('left_at')
                ->whereIn(DB::raw('DATE_FORMAT(joined_at, "%Y-%m")'), array_keys($months))
                ->selectRaw('DATE_FORMAT(joined_at, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            // Get all task data in a single optimized query
            $taskStats = DB::table('project_tasks')
                ->join('projects', 'project_tasks.project_id', '=', 'projects.id')
                ->join('project_teams', 'projects.id', '=', 'project_teams.project_id')
                ->where('project_teams.team_id', $teamId)
                ->whereIn(DB::raw('DATE_FORMAT(project_tasks.created_at, "%Y-%m")'), array_keys($months))
                ->selectRaw('DATE_FORMAT(project_tasks.created_at, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            // Prepare final arrays with 0 defaults
            $memberData = [];
            $taskData = [];
            $monthLabels = [];

            foreach ($months as $key => $label) {
                $monthLabels[] = $label;
                $memberData[] = $memberStats[$key] ?? 0;
                $taskData[] = $taskStats[$key] ?? 0;
            }

            return [
                'months' => $monthLabels,
                'member_growth' => $memberData,
                'task_growth' => $taskData,
            ];
        });
    }

    public function addMember(string $teamId, int $employeeId): TeamMember
    {
        return DB::transaction(function () use ($teamId, $employeeId) {
            // Check if member already exists and is active
            $existingMember = TeamMember::where('team_id', $teamId)
                ->where('employee_id', $employeeId)
                ->whereNull('left_at')
                ->first();

            if ($existingMember) {
                throw new \Exception('Employee is already a member of this team');
            }

            // Add new member
            $member = TeamMember::updateOrCreate(
                [
                    'team_id' => $teamId,
                    'employee_id' => $employeeId,
                ],
                [
                    'joined_at' => now(),
                    'left_at' => null,
                ]
            );

            // Clear caches
            $this->clearTeamCaches($teamId);

            return $member->load(['employee', 'employee.user', 'employee.jobInformation']);
        });
    }

    public function removeMember(string $teamId, int $employeeId): TeamMember
    {
        return DB::transaction(function () use ($teamId, $employeeId) {
            $member = TeamMember::where('team_id', $teamId)
                ->where('employee_id', $employeeId)
                ->whereNull('left_at')
                ->first();

            if (! $member) {
                throw new \Exception('Employee is not a member of this team');
            }

            $member->update(['left_at' => now()]);

            $this->clearTeamCaches($teamId);

            return $member->load(['employee', 'employee.user', 'employee.jobInformation']);
        });
    }

    /**
     * Clear all caches related to team statistics
     */
    private function clearTeamCaches(?string $teamId = null): void
    {
        // Clear general statistics cache
        cache()->forget(CacheConstants::CACHE_KEY_TEAM_STATISTICS.now()->format('Y-m-d-H'));
        cache()->forget(CacheConstants::CACHE_KEY_TEAM_STATISTICS.now()->subHour()->format('Y-m-d-H'));

        // Clear team-specific caches if teamId is provided
        if ($teamId) {
            cache()->forget(CacheConstants::CACHE_KEY_TEAM_STATISTICS.$teamId.'_'.now()->format('Y-m-d-H'));
            cache()->forget(CacheConstants::CACHE_KEY_TEAM_STATISTICS.$teamId.'_'.now()->subHour()->format('Y-m-d-H'));
            cache()->forget(CacheConstants::CACHE_KEY_TEAM_CHART_DATA.$teamId.'_'.now()->format('Y-m-d'));
            cache()->forget(CacheConstants::CACHE_KEY_TEAM_CHART_DATA.$teamId.'_'.now()->subDay()->format('Y-m-d'));
        }
    }
}
