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
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
            new Middleware(PermissionMiddleware::using(['project-export']), only: ['exportProgress']),
            new Middleware(EnsureProjectMembership::class, only: ['show', 'exportProgress']),
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

    /**
     * Stream a client-facing PDF report of a single project's progress:
     * task breakdown by status, completion percentage, and timeline.
     */
    public function exportProgress(string $id)
    {
        $project = Project::with([
            'projectLeader.user',
            'projectLeader.jobInformation',
            'teams',
            'tasks.assignee.user',
        ])->findOrFail($id);

        $tasks = $project->tasks;
        $totalTasks = $tasks->count();
        $doneTasks = $tasks->where('status', 'done')->values();
        $ongoingTasks = $tasks->whereIn('status', ['in_progress', 'review'])->values();
        $pendingTasks = $tasks->where('status', 'todo')->values();
        $cancelledTasks = $tasks->where('status', 'cancelled')->values();
        $progress = $totalTasks > 0 ? round(($doneTasks->count() / $totalTasks) * 100) : 0;

        $timeline = $this->buildTimeline($project, $progress);

        $pdf = Pdf::loadView('pdf.project-progress', [
            'project' => $project,
            'totalTasks' => $totalTasks,
            'doneTasks' => $doneTasks,
            'ongoingTasks' => $ongoingTasks,
            'pendingTasks' => $pendingTasks,
            'cancelledTasks' => $cancelledTasks,
            'progress' => $progress,
            'timeline' => $timeline,
            'generatedAt' => Carbon::now(),
        ])->setPaper('a4');

        $filename = str_replace(' ', '-', $project->name).'-progress-report.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Elapsed/expected-vs-actual pace, mirroring the frontend's
     * getProjectHealth() so the PDF and the in-app deadline banner agree.
     */
    private function buildTimeline(Project $project, int $actualProgress): array
    {
        if (! $project->start_date || ! $project->end_date) {
            return [
                'daysElapsed' => null,
                'totalDurationDays' => null,
                'daysRemaining' => null,
                'expectedProgress' => null,
                'status' => 'No timeline set',
            ];
        }

        $start = Carbon::parse($project->start_date)->startOfDay();
        $end = Carbon::parse($project->end_date)->startOfDay();
        $today = Carbon::now()->startOfDay();

        $totalDurationDays = max(1, $start->diffInDays($end));
        $daysElapsed = min($totalDurationDays, max(0, $start->diffInDays($today, false)));
        $daysRemaining = $today->diffInDays($end, false);
        $expectedProgress = (int) round(($daysElapsed / $totalDurationDays) * 100);

        if (in_array($project->status, ['completed', 'cancelled'])) {
            $status = $project->status === 'completed' ? 'Completed' : 'Cancelled';
        } elseif ($daysRemaining < 0 && $actualProgress < 100) {
            $status = 'Overdue';
        } elseif (($expectedProgress - $actualProgress) >= 10) {
            $status = 'Behind Schedule';
        } else {
            $status = 'On Track';
        }

        return [
            'daysElapsed' => $daysElapsed,
            'totalDurationDays' => $totalDurationDays,
            'daysRemaining' => $daysRemaining,
            'expectedProgress' => $expectedProgress,
            'status' => $status,
        ];
    }
}
