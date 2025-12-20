<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Helpers\ResponseHelper;
use App\Http\Middleware\EnsureProjectMembership;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectResource;
use App\Interfaces\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectController extends Controller implements HasMiddleware
{
    private ProjectRepositoryInterface $projectRepository;

    public function __construct(ProjectRepositoryInterface $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']), only: ['index', 'getAllPaginated', 'show', 'getStatistics']),
            new Middleware(PermissionMiddleware::using(['project-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['project-delete']), only: ['destroy']),
            new Middleware(EnsureProjectMembership::class, only: ['show']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $projects = $this->projectRepository->getAll(
                $request->search,
                $request->status,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Projects Retrieved Successfully', ProjectResource::collection($projects), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request): JsonResponse
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:' . implode(',', array_column(ProjectStatus::cases(), 'value')),
            'row_per_page' => 'required|integer',
        ]);

        try {
            $projects = $this->projectRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['status'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Projects Retrieved Successfully', PaginateResource::make($projects, ProjectResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $request = $request->validated();

        try {
            $project = $this->projectRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Project Created Successfully', new ProjectResource($project), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Project Retrieved Successfully', new ProjectResource($project), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectUpdateRequest $request, string $id): JsonResponse
    {
        $request = $request->validated();

        try {
            $project = $this->projectRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Project Updated Successfully', new ProjectResource($project), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->projectRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Project Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get project statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->projectRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Project Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
