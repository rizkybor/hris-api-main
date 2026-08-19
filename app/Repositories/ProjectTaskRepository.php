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
        $task->update($taskDto->toArray());

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
}
