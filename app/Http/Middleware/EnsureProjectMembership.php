<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Models\Project;
use App\Models\TeamMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureProjectMembership
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        }

        if (!$user->hasRole('employee')) {
            return $next($request);
        }

        $employee = $user->employeeProfile;
        if (!$employee) {
            return ResponseHelper::jsonResponse(false, 'Forbidden', null, 403);
        }

        $projectId = $request->route('project') ?? $request->route('id');
        if (!$projectId) {
            return ResponseHelper::jsonResponse(false, 'Project ID Missing', null, 400);
        }

        $project = Project::with('teams')->find($projectId);
        if (!$project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }

        $isLeader = ($project->project_leader_id === $employee->id);

        $jobInfoTeamId = $employee->jobInformation->team_id ?? null;
        $teamMemberIds = TeamMember::where('employee_id', $employee->id)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();
        $teamIds = array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $teamMemberIds
        )));

        $projectTeamIds = $project->teams->pluck('id')->toArray();
        $isTeamAssigned = !empty(array_intersect($projectTeamIds, $teamIds));

        if (!$isLeader && !$isTeamAssigned) {
            return ResponseHelper::jsonResponse(false, 'Forbidden', null, 403);
        }

        return $next($request);
    }
}
