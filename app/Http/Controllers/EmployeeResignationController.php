<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\CompanyAsset;
use App\Models\EmployeeResignation;
use App\Models\JobInformation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class EmployeeResignationController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['employee-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['employee-edit']), only: ['store', 'complete']),
        ];
    }

    /**
     * Pending offboarding across the company, soonest last-working-date first.
     */
    public function index(Request $request)
    {
        try {
            $resignations = EmployeeResignation::with('employee.user', 'employee.jobInformation')
                ->when($request->status, fn ($q, $status) => $q->where('status', $status))
                ->orderBy('last_working_date')
                ->get();

            return ResponseHelper::jsonResponse(true, 'Resignations Retrieved Successfully', $resignations, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Latest resignation record for an employee, plus an offboarding checklist
     * (currently assigned company assets that need to be returned).
     */
    public function show(string $employeeId)
    {
        try {
            $resignation = EmployeeResignation::with('employee.user', 'processedBy')
                ->where('employee_id', $employeeId)
                ->latest('resignation_date')
                ->first();

            $assignedAssets = CompanyAsset::where('assigned_to', $employeeId)
                ->where('status', 'assigned')
                ->get(['id', 'asset_code', 'name', 'category']);

            return ResponseHelper::jsonResponse(true, 'Resignation Retrieved Successfully', [
                'resignation' => $resignation,
                'assets_to_return' => $assignedAssets,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request, string $employeeId)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:resign,terminated',
            'reason' => 'nullable|string',
            'resignation_date' => 'required|date',
            'last_working_date' => 'nullable|date|after_or_equal:resignation_date',
        ]);

        try {
            $resignation = DB::transaction(function () use ($validated, $employeeId, $request) {
                $resignation = EmployeeResignation::create([
                    ...$validated,
                    'employee_id' => $employeeId,
                    'status' => 'pending',
                    'processed_by' => $request->user()->id,
                ]);

                $jobInfo = JobInformation::where('employee_id', $employeeId)->first();
                $jobInfo?->update([
                    'status' => $validated['type'] === 'terminated' ? 'terminated' : 'resigned',
                ]);

                return $resignation;
            });

            return ResponseHelper::jsonResponse(true, 'Resignation Recorded Successfully', $resignation, 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Finalize offboarding (exit interview done, assets accounted for).
     */
    public function complete(Request $request, string $id)
    {
        $validated = $request->validate([
            'exit_interview_notes' => 'nullable|string',
        ]);

        try {
            $resignation = EmployeeResignation::findOrFail($id);
            $resignation->update([
                ...$validated,
                'status' => 'completed',
                'processed_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Offboarding Completed Successfully', $resignation, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Resignation Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
