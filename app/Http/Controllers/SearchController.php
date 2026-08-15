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

        try {
            $employees = EmployeeProfile::query()
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

            $projects = Project::query()
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name', 'status', 'priority']);

            $teams = Team::query()
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name']);

            $tasks = ProjectTask::query()
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
