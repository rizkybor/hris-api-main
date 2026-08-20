<?php

namespace App\Repositories;

use App\DTOs\ProjectTaskDto;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Models\ProjectTask;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProjectTaskRepository implements ProjectTaskRepositoryInterface
{
    /**
     * Lower rank = higher severity. Mirrors the declared order of
     * App\Enums\TaskPriority.
     */
    private const PRIORITY_RANK = [
        'urgent' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
    ];

    public function __construct(private CloudinaryManager $cloudinary) {}

    public function getAll(
        ?string $search,
        ?int $projectId,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = ProjectTask::with(['project', 'assignee.user'])
            ->where(function ($query) use ($search, $projectId) {
                if ($search) {
                    $query->search($search);
                }
                if ($projectId) {
                    $query->where('project_id', $projectId);
                }
            })
            ->orderBy('status')
            ->orderBy('position');

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
        ?int $projectId,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            $projectId,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): ProjectTask {
        return ProjectTask::with(['project', 'assignee.user'])
            ->findOrFail($id);
    }

    public function getByProjectId(int $projectId): Collection
    {
        return ProjectTask::with(['assignee.user'])
            ->where('project_id', $projectId)
            ->orderBy('status')
            ->orderBy('position')
            ->get();
    }

    public function getMyTasks(int $employeeId, ?int $limit, bool $includeCompleted = false): Collection
    {
        $query = ProjectTask::with(['project'])
            ->where('assignee_id', $employeeId)
            ->when(! $includeCompleted, fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'review']))
            ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC");

        if ($limit) {
            $query->take($limit);
        }

        return $query->get();
    }

    public function create(array $data): ProjectTask
    {
        $taskDto = ProjectTaskDto::fromArray($data);
        $taskArray = $taskDto->toArray();
        $taskArray['position'] = $this->positionForPriority($taskArray['project_id'], $taskArray['status'], $taskArray['priority']);

        $task = ProjectTask::create($taskArray);

        if (isset($data['image'])) {
            $task->load('project');
            $publicId = $this->cloudinary->uploadImage(
                $data['image'],
                CloudinaryFolders::projectFiles(),
                CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($task->project->name, $task->project_id).'-task-'.$task->id)
            );
            $task->update(['image' => $publicId]);
        }

        return $task;
    }

    public function update(string $id, array $data): ProjectTask
    {
        $task = $this->getById($id);
        $taskDto = ProjectTaskDto::fromArrayForUpdate($data, $task);
        $updateArray = $taskDto->toArray();

        if (array_key_exists('position', $data) && $data['position'] !== null) {
            // Explicit position from the Kanban drag-and-drop move action --
            // already computed client-side as the fractional midpoint of
            // its new neighbors.
            $updateArray['position'] = $data['position'];
        } elseif (
            (isset($data['status']) && $data['status'] !== $task->status)
            || (isset($data['priority']) && $data['priority'] !== $task->priority)
        ) {
            // Status or priority changed (e.g. via the task edit form, not a
            // drag) with no explicit position -- reposition into the right
            // priority cluster of the (possibly new) column.
            $updateArray['position'] = $this->positionForPriority($updateArray['project_id'], $updateArray['status'], $updateArray['priority']);
        }

        $task->update($updateArray);

        if (! empty($data['remove_image'])) {
            $this->cloudinary->delete($task->image);
            $task->update(['image' => null]);
        } elseif (isset($data['image'])) {
            $this->cloudinary->delete($task->image);

            $publicId = $this->cloudinary->uploadImage(
                $data['image'],
                CloudinaryFolders::projectFiles(),
                CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($task->project->name, $task->project_id).'-task-'.$task->id)
            );
            $task->update(['image' => $publicId]);
        }

        return $task;
    }

    public function delete(string $id): ProjectTask
    {
        $task = $this->getById($id);

        $this->cloudinary->delete($task->image);

        $task->delete();

        return $task;
    }

    /**
     * Trello-style fractional position, placed so the task lands at the end
     * of its own-or-better priority cluster and above any strictly worse
     * priority task in the column -- e.g. a new "high" task goes below all
     * urgent/high tasks but above every medium/low one. Works even when the
     * column isn't already perfectly priority-grouped (e.g. after manual
     * drags), since it only reasons from min/max aggregates rather than
     * assuming existing order. Manual drag-and-drop (explicit `position`)
     * always takes precedence over this and is never overridden by it.
     */
    private function positionForPriority(int $projectId, string $status, string $priority): float
    {
        $rank = self::PRIORITY_RANK[$priority] ?? 99;
        $betterOrEqualPriorities = array_keys(array_filter(self::PRIORITY_RANK, fn ($r) => $r <= $rank));
        $worsePriorities = array_keys(array_filter(self::PRIORITY_RANK, fn ($r) => $r > $rank));

        $betterOrEqualMax = ProjectTask::where('project_id', $projectId)
            ->where('status', $status)
            ->whereIn('priority', $betterOrEqualPriorities)
            ->max('position');

        $worseMin = $worsePriorities === [] ? null : ProjectTask::where('project_id', $projectId)
            ->where('status', $status)
            ->whereIn('priority', $worsePriorities)
            ->min('position');

        if ($betterOrEqualMax !== null && $worseMin !== null) {
            return ($betterOrEqualMax + $worseMin) / 2;
        }
        if ($betterOrEqualMax !== null) {
            return $betterOrEqualMax + 1000;
        }
        if ($worseMin !== null) {
            return $worseMin - 1000;
        }

        return 1000;
    }
}
