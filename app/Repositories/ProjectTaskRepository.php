<?php

namespace App\Repositories;

use App\DTOs\ProjectTaskDto;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Models\ProjectTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

    public function create(array $data): ProjectTask
    {
        $taskDto = ProjectTaskDto::fromArray($data);
        $taskArray = $taskDto->toArray();

        return ProjectTask::create($taskArray);
    }

    public function update(string $id, array $data): ProjectTask
    {
        $task = $this->getById($id);
        $taskDto = ProjectTaskDto::fromArrayForUpdate($data, $task);
        $task->update($taskDto->toArray());

        return $task;
    }

    public function delete(string $id): ProjectTask
    {
        $task = $this->getById($id);
        $task->delete();

        return $task;
    }
}
