<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\ProjectDto;
use App\Interfaces\ProjectRepositoryInterface;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(private CloudinaryManager $cloudinary) {}

    public function getAll(
        ?string $search,
        ?string $status,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Project::with(['projectLeader', 'projectLeader.user', 'projectLeader.jobInformation', 'teams', 'members', 'members.user', 'tasks'])
            ->where(function ($query) use ($search, $status) {
                if ($search) {
                    $query->search($search);
                }

                if ($status) {
                    $query->where('status', $status);
                }
            })
            ->withCount('teams')
            ->withCount('tasks')
            ->orderByDesc('created_at');

        if (Auth::user()->hasRole('staff')) {
            $employeeId = Auth::user()->employeeProfile->id;

            // Get team ID from JobInformation
            $jobInfoTeamId = Auth::user()->employeeProfile->jobInformation->team_id ?? null;

            // Get all team IDs that the employee is currently a member of (not left)
            $teamMemberIds = TeamMember::where('employee_id', $employeeId)
                ->whereNull('left_at')
                ->pluck('team_id')
                ->toArray();

            // Combine team IDs from JobInformation and TeamMember
            $teamIds = array_unique(array_filter(array_merge(
                $jobInfoTeamId ? [$jobInfoTeamId] : [],
                $teamMemberIds
            )));

            $query->where(function ($q) use ($employeeId, $teamIds) {
                // Show projects where employee is the leader
                $q->where('project_leader_id', $employeeId);

                // OR show projects where employee's team is assigned
                if (! empty($teamIds)) {
                    $q->orWhereHas('teams', function ($teamQuery) use ($teamIds) {
                        $teamQuery->whereIn('teams.id', $teamIds);
                    });
                }

                // OR show projects where the employee was individually
                // picked as a member ("employee" assignment mode)
                $q->orWhereHas('members', function ($memberQuery) use ($employeeId) {
                    $memberQuery->where('employee_profiles.id', $employeeId);
                });
            });
        }

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
        ?string $status,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            $status,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): Project {
        return Project::with([
            'projectLeader',
            'projectLeader.user',
            'projectLeader.jobInformation',
            'teams' => function ($query) {
                $query->withCount('members');
            },
            'teams.leader',
            'members',
            'members.user',
            'tasks',
        ])
            ->findOrFail($id);
    }

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $projectDto = ProjectDto::fromArray($data);
            $project = Project::create($projectDto->toArray());

            if (isset($data['photo'])) {
                $publicId = $this->cloudinary->uploadImage(
                    $data['photo'],
                    CloudinaryFolders::projectFiles(),
                    CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-photo')
                );
                $project->update(['photo' => $publicId]);
            }

            $this->applyTeamAssignment($project, $data);

            $this->clearStatisticsCache();

            return $project;
        });
    }

    public function update(string $id, array $data): Project
    {
        return DB::transaction(function () use ($id, $data) {
            $project = $this->getById($id);

            $projectDto = ProjectDto::fromArrayForUpdate($data, $project);
            $project->update($projectDto->toArray());

            if (isset($data['photo'])) {
                $this->cloudinary->delete($project->photo);

                $publicId = $this->cloudinary->uploadImage(
                    $data['photo'],
                    CloudinaryFolders::projectFiles(),
                    CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-photo')
                );
                $project->update(['photo' => $publicId]);
            }

            if (array_key_exists('team_assignment_mode', $data) || array_key_exists('team_id', $data) || array_key_exists('member_employee_ids', $data)) {
                $this->applyTeamAssignment($project, $data);
            }

            $this->clearStatisticsCache();

            return $project;
        });
    }

    /**
     * "team" mode: exactly one Team supplies both the project leader (its
     * team_lead_id) and the member roster (its active TeamMembers) --
     * individual picks are cleared. "employee" mode: the reverse -- an
     * individually-picked member roster is stored directly and any Team
     * assignment is cleared, leaving project_leader_id as whatever the
     * request/DTO already set.
     */
    private function applyTeamAssignment(Project $project, array $data): void
    {
        $mode = $data['team_assignment_mode'] ?? $project->team_assignment_mode ?? 'employee';

        if ($mode === 'team') {
            $team = Team::with('leader.employeeProfile')->find($data['team_id'] ?? null);

            $this->assignTeams($project->id, $team ? [$team->id] : []);
            $this->assignMembers($project->id, []);

            if ($team?->leader?->employeeProfile) {
                $project->update(['project_leader_id' => $team->leader->employeeProfile->id]);
            }

            return;
        }

        $this->assignTeams($project->id, []);
        $this->assignMembers($project->id, $data['member_employee_ids'] ?? []);
    }

    public function delete(string $id): Project
    {
        return DB::transaction(function () use ($id) {
            $project = $this->getById($id);

            $this->cloudinary->delete($project->photo);

            $project->delete();

            $this->clearStatisticsCache();

            return $project;
        });
    }

    public function getStatistics(): array
    {
        // Cache key for statistics
        $cacheKey = CacheConstants::CACHE_KEY_PROJECT_STATISTICS.now()->format('Y-m-d-H');

        // Cache for 1 hour
        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            // Get all project statistics in a single optimized query
            $projectStats = Project::selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as active,
                COUNT(CASE WHEN status = ? THEN 1 END) as completed,
                COUNT(CASE WHEN status = ? THEN 1 END) as on_hold,
                COUNT(CASE WHEN YEAR(created_at) = ? AND MONTH(created_at) = ? THEN 1 END) as added_this_month,
                COUNT(CASE WHEN status = ? AND created_at <= ? THEN 1 END) as active_last_week,
                SUM(budget) as total_budget
            ', [
                'active',
                'completed',
                'on_hold',
                now()->year,
                now()->month,
                'active',
                now()->subWeek()->endOfWeek(),
            ])->first();

            // Get task statistics in a single optimized query
            $taskStats = DB::table('project_tasks')
                ->selectRaw('
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN status = ? THEN 1 END) as completed_tasks,
                    COUNT(CASE WHEN status = ? THEN 1 END) as in_progress_tasks,
                    COUNT(CASE WHEN YEAR(created_at) = ? AND MONTH(created_at) = ? THEN 1 END) as tasks_this_month
                ', [
                    'done',
                    'in_progress',
                    now()->year,
                    now()->month,
                ])->first();

            $totalProjects = $projectStats->total;
            $activeProjects = $projectStats->active;
            $activeProjectsLastWeek = $projectStats->active_last_week;
            $projectsThisMonth = $projectStats->added_this_month;

            // Calculate changes
            $activeProjectsChange = $activeProjects - $activeProjectsLastWeek;

            // Calculate completion rate
            $completionRate = $taskStats->total_tasks > 0
                ? round(($taskStats->completed_tasks / $taskStats->total_tasks) * 100)
                : 0;

            return [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'completed' => $projectStats->completed ?? 0,
                'on_hold' => $projectStats->on_hold ?? 0,
                'added_this_month' => $projectsThisMonth,
                'active_change' => $activeProjectsChange,
                'total_tasks' => $taskStats->total_tasks ?? 0,
                'completed_tasks' => $taskStats->completed_tasks ?? 0,
                'in_progress_tasks' => $taskStats->in_progress_tasks ?? 0,
                'tasks_this_month' => $taskStats->tasks_this_month ?? 0,
                'completion_rate' => $completionRate,
                'total_budget' => (float) ($projectStats->total_budget ?? 0),
                'budget_by_month' => $this->getBudgetByMonth(),
            ];
        });
    }

    /**
     * Sum of project budgets grouped by the month projects started, used to
     * chart budget over time (both monthly-within-a-year and year totals
     * are derived from this on the frontend).
     */
    private function getBudgetByMonth(): array
    {
        return Project::query()
            ->whereNotNull('budget')
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month')
            ->selectRaw('SUM(budget) as total_budget')
            ->selectRaw('COUNT(*) as total_projects')
            ->groupBy(DB::raw('DATE_FORMAT(start_date, "%Y-%m")'))
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    private function assignTeams(int $projectId, array $teamIds): void
    {
        $project = Project::findOrFail($projectId);

        if (empty($teamIds)) {
            $project->teams()->sync([]);

            return;
        }

        $syncPayload = [];
        foreach ($teamIds as $teamId) {
            $syncPayload[$teamId] = ['assigned_at' => now()];
        }

        $project->teams()->sync($syncPayload);
    }

    private function assignMembers(int $projectId, array $employeeIds): void
    {
        Project::findOrFail($projectId)->members()->sync($employeeIds);
    }

    private function clearStatisticsCache(): void
    {
        $cacheKey = CacheConstants::CACHE_KEY_PROJECT_STATISTICS.now()->format('Y-m-d-H');
        cache()->forget($cacheKey);
    }
}
