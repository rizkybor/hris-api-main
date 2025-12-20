<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Team\TeamAddMemberRequest;
use App\Http\Requests\Team\TeamRemoveMemberRequest;
use App\Http\Requests\Team\TeamStoreRequest;
use App\Http\Requests\Team\TeamUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\TeamResource;
use App\Interfaces\TeamRepositoryInterface;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class TeamController extends Controller implements HasMiddleware
{
    private TeamRepositoryInterface $teamRepository;

    public function __construct(TeamRepositoryInterface $teamRepository)
    {
        $this->teamRepository = $teamRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['team-list|team-create|team-edit|team-delete']), only: ['index', 'getAllPaginated', 'show', 'getStatistics', 'getTeamStatistics', 'getTeamChartData']),
            new Middleware(PermissionMiddleware::using(['team-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['team-edit']), only: ['update', 'addMember', 'removeMember']),
            new Middleware(PermissionMiddleware::using(['team-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $teams = $this->teamRepository->getAll(
                $request->search,
                $request->leader_id,
                $request->status,
                $request->department,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Teams Retrieved Successfully', TeamResource::collection($teams), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request): JsonResponse
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'leader_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string',
            'department' => 'nullable|string',
            'row_per_page' => 'required|integer|min:1',
        ]);

        try {
            $teams = $this->teamRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['leader_id'] ?? null,
                $request['status'] ?? null,
                $request['department'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Teams Retrieved Successfully', PaginateResource::make($teams, TeamResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TeamStoreRequest $request): JsonResponse
    {
        $request = $request->validated();

        try {
            $team = $this->teamRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Team Created Successfully', new TeamResource($team), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Team $team): JsonResponse
    {
        try {
            $team = $this->teamRepository->getById($team->id);

            return ResponseHelper::jsonResponse(true, 'Team Retrieved Successfully', new TeamResource($team), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TeamUpdateRequest $request, Team $team): JsonResponse
    {
        $request = $request->validated();

        try {
            $team = $this->teamRepository->update($team->id, $request);

            return ResponseHelper::jsonResponse(true, 'Team Updated Successfully', new TeamResource($team), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team): JsonResponse
    {
        try {
            $this->teamRepository->delete($team->id);

            return ResponseHelper::jsonResponse(true, 'Team Deleted Successfully', null, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get team statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->teamRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Team Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get specific team statistics
     */
    public function getTeamStatistics(Team $team): JsonResponse
    {
        try {
            $statistics = $this->teamRepository->getTeamStatistics($team->id);

            return ResponseHelper::jsonResponse(true, 'Team Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get specific team chart data
     */
    public function getTeamChartData(Team $team): JsonResponse
    {
        try {
            $chartData = $this->teamRepository->getTeamChartData($team->id);

            return ResponseHelper::jsonResponse(true, 'Team Chart Data Retrieved Successfully', $chartData, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Add member to team
     */
    public function addMember(TeamAddMemberRequest $request, Team $team): JsonResponse
    {
        $validated = $request->validated();

        try {
            $member = $this->teamRepository->addMember($team->id, $validated['employee_id']);

            return ResponseHelper::jsonResponse(true, 'Member Added Successfully', $member, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Remove member from team
     */
    public function removeMember(TeamRemoveMemberRequest $request, Team $team): JsonResponse
    {
        $validated = $request->validated();

        try {
            $member = $this->teamRepository->removeMember($team->id, $validated['employee_id']);

            return ResponseHelper::jsonResponse(true, 'Member Removed Successfully', $member, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
