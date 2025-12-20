<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\ProjectDto;
use App\Interfaces\ProjectRepositoryInterface;
use App\Models\Project;
use App\Models\TeamMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $status,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Project::with(['projectLeader', 'projectLeader.user', 'projectLeader.jobInformation', 'teams', 'tasks'])
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

        if (Auth::user()->hasRole('employee')) {
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
                $photoPath = $data['photo']->store('project-photos', 'public');
                $project->update(['photo' => $photoPath]);
            }

            $this->assignTeams($project->id, $data['teams'] ?? []);

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
                if ($project->photo && Storage::disk('public')->exists($project->photo)) {
                    Storage::disk('public')->delete($project->photo);
                }

                $photoPath = $data['photo']->store('project-photos', 'public');
                $project->update(['photo' => $photoPath]);
            }

            $this->assignTeams($project->id, $data['teams'] ?? []);

            $this->clearStatisticsCache();

            return $project;
        });
    }

    public function delete(string $id): Project
    {
        return DB::transaction(function () use ($id) {
            $project = $this->getById($id);

            if ($project->photo && Storage::disk('public')->exists($project->photo)) {
                Storage::disk('public')->delete($project->photo);
            }

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
                COUNT(CASE WHEN status = ? AND created_at <= ? THEN 1 END) as active_last_week
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
            ];
        });
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

    private function clearStatisticsCache(): void
    {
        $cacheKey = CacheConstants::CACHE_KEY_PROJECT_STATISTICS.now()->format('Y-m-d-H');
        cache()->forget($cacheKey);
    }
}
