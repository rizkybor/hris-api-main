<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Team;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '' || strlen($query) < 2) {
            return ResponseHelper::jsonResponse(true, 'Search Results Retrieved Successfully', [
                'employees' => [],
                'projects' => [],
                'teams' => [],
                'tasks' => [],
            ], 200);
        }

        // Each group only for users allowed to see that list.
        $user = $request->user();
        $allowed = fn (string ...$permissions) => collect($permissions)->contains(fn ($p) => $user->can($p));

        try {
            $employees = ! $allowed('employee-list', 'employee-menu') ? collect() : EmployeeProfile::query()
                ->with('user', 'jobInformation.team')
                ->whereHas('user', function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%");
                })
                ->orWhere('code', 'like', "%{$query}%")
                ->limit(5)
                ->get()
                ->map(fn ($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->user?->name,
                    'email' => $employee->user?->email,
                    'job_title' => $employee->jobInformation?->job_title,
                    'department' => $employee->jobInformation?->team?->department,
                ]);

            $projects = ! $allowed('project-menu', 'project-list') ? collect() : Project::query()
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name', 'status', 'priority']);

            $teams = ! $allowed('team-view', 'team-menu', 'team-list') ? collect() : Team::query()
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name']);

            $tasks = ! $allowed('task-list', 'task-menu', 'project-menu') ? collect() : ProjectTask::query()
                ->with('project:id,name')
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get()
                ->map(fn ($task) => [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project?->name,
                ]);

            return ResponseHelper::jsonResponse(true, 'Search Results Retrieved Successfully', [
                'employees' => $employees,
                'projects' => $projects,
                'teams' => $teams,
                'tasks' => $tasks,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
