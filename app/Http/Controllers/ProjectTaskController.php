<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectTaskStoreRequest;
use App\Http\Requests\ProjectTaskUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectTaskResource;
use App\Interfaces\ProjectTaskRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getByProject(Request $request, int $projectId)
    {
        try {
            $tasks = $this->projectTaskRepository->getByProjectId($projectId);

            return ResponseHelper::jsonResponse(true, 'Project Tasks Retrieved Successfully', ProjectTaskResource::collection($tasks), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(ProjectTaskStoreRequest $request)
    // {
    //     $request = $request->validated();

    //     try {
    //         $task = $this->projectTaskRepository->create($request);

    //         return ResponseHelper::jsonResponse(true, 'Task Created Successfully', new ProjectTaskResource($task), 201);
    //     } catch (\Throwable $e) {
    //         return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
    //     }
    // }

    /**
     * Store a newly created task and save file to private disk.
     */
    public function store(ProjectTaskStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            if ($request->hasFile('document') && $request->file('document')->isValid()) {
                $file = $request->file('document');

                // simpan file di disk company_files
                $path = $file->store('', 'company_files');

                $validated['document_path'] = $path;
                $validated['document_name'] = $file->getClientOriginalName();
                $validated['type_file'] = $file->getClientMimeType();
                $validated['size_file'] = $file->getSize();
            }

            $task = $this->projectTaskRepository->create($validated);

            

            return ResponseHelper::jsonResponse(true, 'Task Created Successfully', new ProjectTaskResource($task), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * Download task file from private disk.
     */
    public function download(string $id)
    {
        $task = $this->projectTaskRepository->getById($id);

        $disk = Storage::disk('company_files');

        if (!isset($task->document_path) || !$disk->exists($task->document_path)) {
            abort(404, 'File not found');
        }

        $filePath = $disk->path($task->document_path);

        return response()->download($filePath, $task->document_name ?? 'task-file');
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectTaskUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $task = $this->projectTaskRepository->update($id, $request);

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
            $this->projectTaskRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Task Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
