<?php

namespace App\Repositories;

use App\DTOs\ProjectTaskDto;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Models\ProjectTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class ProjectTaskRepository implements ProjectTaskRepositoryInterface
{
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
            });

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

        $task = ProjectTask::create($taskArray);

        if (isset($data['image'])) {
            $imagePath = $data['image']->store('task-images', 'public');
            $task->update(['image' => $imagePath]);
        }

        return $task;
    }

    public function update(string $id, array $data): ProjectTask
    {
        $task = $this->getById($id);
        $taskDto = ProjectTaskDto::fromArrayForUpdate($data, $task);
        $task->update($taskDto->toArray());

        if (! empty($data['remove_image'])) {
            if ($task->image && Storage::disk('public')->exists($task->image)) {
                Storage::disk('public')->delete($task->image);
            }
            $task->update(['image' => null]);
        } elseif (isset($data['image'])) {
            if ($task->image && Storage::disk('public')->exists($task->image)) {
                Storage::disk('public')->delete($task->image);
            }

            $imagePath = $data['image']->store('task-images', 'public');
            $task->update(['image' => $imagePath]);
        }

        return $task;
    }

    public function delete(string $id): ProjectTask
    {
        $task = $this->getById($id);

        if ($task->image && Storage::disk('public')->exists($task->image)) {
            Storage::disk('public')->delete($task->image);
        }

        $task->delete();

        return $task;
    }
}
