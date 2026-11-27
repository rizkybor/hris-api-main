<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectTaskStoreRequest;
use App\Http\Requests\ProjectTaskUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectTaskResource;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Models\ProjectTask;
use App\Services\UatTaskSync;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectTaskController extends Controller implements HasMiddleware
{
    private ProjectTaskRepositoryInterface $projectTaskRepository;

    public function __construct(ProjectTaskRepositoryInterface $projectTaskRepository)
    {
        $this->projectTaskRepository = $projectTaskRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['task-list|task-create|task-edit|task-delete']), only: ['index', 'getAllPaginated', 'getByProject', 'getByProjectPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['task-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['task-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['task-delete']), only: ['destroy']),
        ];
    }

    /**
     * Get tasks assigned to the currently authenticated employee.
     */
    public function getMyTasks(Request $request)
    {
        try {
            $employeeId = $request->user()->employeeProfile?->id;

            if (! $employeeId) {
                return ResponseHelper::jsonResponse(true, 'No Employee Profile Found', [], 200);
            }

            $tasks = $this->projectTaskRepository->getMyTasks(
                $employeeId,
                $request->limit ?? 5,
                $request->boolean('include_completed')
            );

            return ResponseHelper::jsonResponse(true, 'My Tasks Retrieved Successfully', ProjectTaskResource::collection($tasks), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $tasks = $this->projectTaskRepository->getAll(
                $request->search,
                $request->project_id,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Tasks Retrieved Successfully', ProjectTaskResource::collection($tasks), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'project_id' => 'nullable|integer',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $tasks = $this->projectTaskRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['project_id'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Tasks Retrieved Successfully', PaginateResource::make($tasks, ProjectTaskResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getByProject(Request $request, int $projectId)
    {
        try {
            $tasks = $this->projectTaskRepository->getByProjectId($projectId);

            return ResponseHelper::jsonResponse(true, 'Project Tasks Retrieved Successfully', ProjectTaskResource::collection($tasks), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectTaskStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $task = $this->projectTaskRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Task Created Successfully', new ProjectTaskResource($task), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $task = $this->projectTaskRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Task Retrieved Successfully', new ProjectTaskResource($task), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectTaskUpdateRequest $request, string $id)
    {
        $user = $request->user();
        $request = $request->validated();

        try {
            $uatTask = ProjectTask::whereKey($id)->whereNotNull('project_uat_item_id')->first();
            if ($uatTask) {
                // A UAT task only reads To Do / Done; its text follows the
                // finding/note and is edited in the UAT tab.
                if (isset($request['status']) && ! in_array($request['status'], UatTaskSync::STATUSES, true)) {
                    return ResponseHelper::jsonResponse(false, 'Task dari UAT hanya bisa berstatus To Do atau Done.', null, 422);
                }
                unset($request['name'], $request['description'], $request['type'], $request['color']);
            }

            $task = $this->projectTaskRepository->update($id, $request);

            // Done on the board = checked off as revised in UAT (and back).
            if ($uatTask && isset($request['status']) && $request['status'] !== $uatTask->status) {
                $item = $uatTask->uatItem;
                $resolved = $request['status'] === TaskStatus::DONE->value;
                if ($item && $item->is_resolved !== $resolved) {
                    $item->update($resolved
                        ? ['is_resolved' => true, 'resolved_at' => now(), 'resolved_by' => $user?->id]
                        : ['is_resolved' => false, 'resolved_at' => null, 'resolved_by' => null]);
                }
                $task->refresh();
            }

            return ResponseHelper::jsonResponse(true, 'Task Updated Successfully', new ProjectTaskResource($task), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            if (ProjectTask::whereKey($id)->whereNotNull('project_uat_item_id')->exists()) {
                return ResponseHelper::jsonResponse(false, 'Task dari UAT dihapus lewat tab UAT (hapus temuan/catatannya).', null, 422);
            }
            $this->projectTaskRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Task Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
