<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Project;
use App\Services\ProjectBillingService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Project detail > billing: the project's value, what has been invoiced for
 * it and what has been paid against those invoices -- see ProjectBillingService.
 */
class ProjectBillingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-expense-list']), only: ['show']),
        ];
    }

    public function show(int $projectId, ProjectBillingService $billing)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }

        $invoices = $billing->invoicesFor([$project->id])->get($project->id, collect());

        return ResponseHelper::jsonResponse(true, 'Project Billing Retrieved Successfully', $billing->summary((float) $project->budget, $invoices), 200);
    }
}
