<?php

namespace App\Http\Controllers;

use App\Enums\WorkLocation;
use App\Helpers\ResponseHelper;
use App\Http\Requests\EmployeeProfileStoreRequest;
use App\Http\Requests\EmployeeProfileUpdateRequest;
use App\Http\Resources\EmployeeProfileResource;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class EmployeeProfileController extends Controller implements HasMiddleware
{
    private EmployeeProfileRepositoryInterface $employeeProfileRepository;

    public function __construct(EmployeeProfileRepositoryInterface $employeeProfileRepository)
    {
        $this->employeeProfileRepository = $employeeProfileRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['employee-list|employee-create|employee-edit|employee-delete']), only: ['index', 'getAllPaginated', 'show', 'getStatistics']),
            new Middleware(PermissionMiddleware::using(['employee-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['employee-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['employee-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['profile-view']), only: ['getMyProfile', 'getPerformanceStatistics']),
            new Middleware(PermissionMiddleware::using(['team-view']), only: ['getMyTeam', 'getMyTeamMembers', 'getMyTeamProjects']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $employees = $this->employeeProfileRepository->getAll(
                $request->search,
                $request->status,
                $request->type,
                $request->work_location,
                $request->project_id,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Employee Retrieved Successfully', EmployeeProfileResource::collection($employees), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request): JsonResponse
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'type' => 'nullable|string',
            'work_location' => 'nullable|string|in:'.implode(',', array_column(WorkLocation::cases(), 'value')),
            'project_id' => 'nullable|integer',
            'row_per_page' => 'required|integer|min:1',
        ]);

        try {
            $employees = $this->employeeProfileRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['status'] ?? null,
                $request['type'] ?? null,
                $request['work_location'] ?? null,
                $request['project_id'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Employee Retrieved Successfully', PaginateResource::make($employees, EmployeeProfileResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeProfileStoreRequest $request): JsonResponse
    {
        $request = $request->validated();

        try {
            $employee = $this->employeeProfileRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Employee Created Successfully', new EmployeeProfileResource($employee), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $employee = $this->employeeProfileRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Employee Retrieved Successfully', new EmployeeProfileResource($employee), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeProfileUpdateRequest $request, string $id): JsonResponse
    {
        $request = $request->validated();

        try {
            $employee = $this->employeeProfileRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Employee Updated Successfully', new EmployeeProfileResource($employee), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->employeeProfileRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Employee Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get employee statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->employeeProfileRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Employee statistics fetched successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get employee performance statistics
     */
    public function getPerformanceStatistics(string $id): JsonResponse
    {
        try {
            $statistics = $this->employeeProfileRepository->getPerformanceStatistics($id);

            return ResponseHelper::jsonResponse(true, 'Employee performance statistics fetched successfully', $statistics, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get my profile (authenticated employee)
     */
    public function getMyProfile(): JsonResponse
    {
        try {
            $employee = $this->employeeProfileRepository->getMyProfile();

            return ResponseHelper::jsonResponse(true, 'Employee Profile Retrieved Successfully', new EmployeeProfileResource($employee), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Profile Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get my team (authenticated employee)
     */
    public function getMyTeam(): JsonResponse
    {
        try {
            $team = $this->employeeProfileRepository->getMyTeam();

            return ResponseHelper::jsonResponse(true, 'Team Retrieved Successfully', new TeamResource($team), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Team Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get my team members (authenticated employee)
     */
    public function getMyTeamMembers(): JsonResponse
    {
        try {
            $members = $this->employeeProfileRepository->getMyTeamMembers();

            return ResponseHelper::jsonResponse(true, 'Team members Retrieved Successfully', TeamMemberResource::collection($members), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Team Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get my team projects (authenticated employee)
     */
    public function getMyTeamProjects(): JsonResponse
    {
        try {
            $projects = $this->employeeProfileRepository->getMyTeamProjects();

            return ResponseHelper::jsonResponse(true, 'Team projects Retrieved Successfully', ProjectResource::collection($projects), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Team Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
