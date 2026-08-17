<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectCalculationRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectCalculationResource;
use App\Models\ProjectCalculation;
use App\Models\ProjectRateSetting;
use App\Services\ProjectCalculator\ProjectCalculatorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectCalculationController extends Controller implements HasMiddleware
{
    public function __construct(protected ProjectCalculatorService $calculatorService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-calculator-menu']), only: ['index', 'show', 'getStatistics', 'preview']),
            new Middleware(PermissionMiddleware::using(['project-calculator-create']), only: ['store', 'preview']),
            new Middleware(PermissionMiddleware::using(['project-calculator-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['project-calculator-delete']), only: ['destroy']),
        ];
    }

    private function currentRateSetting(): ProjectRateSetting
    {
        return ProjectRateSetting::first() ?? ProjectRateSetting::create([
            'team_monthly_cost' => 10800000,
            'productive_hours_per_person' => 120,
            'team_size' => 3,
            'margin_multiplier' => 2.5,
            'pm_overhead_percent' => 12,
            'default_infra_setup_cost' => 2000000,
        ]);
    }

    public function index(Request $request)
    {
        try {
            $query = ProjectCalculation::with('creator')
                ->when($request->search, fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('client_name', 'like', '%'.$request->search.'%'))
                ->when($request->scenario, fn ($q) => $q->where('scenario', $request->scenario))
                ->latest();

            $calculations = $query->paginate($request->row_per_page ?? 10);

            return ResponseHelper::jsonResponse(true, 'Project Calculations Retrieved Successfully', PaginateResource::make($calculations, ProjectCalculationResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getStatistics()
    {
        try {
            $stats = ProjectCalculation::selectRaw('
                COUNT(*) as total_calculations,
                COALESCE(SUM(grand_total), 0) as total_value,
                COALESCE(AVG(grand_total), 0) as average_value,
                COUNT(CASE WHEN scenario = "feature" THEN 1 END) as total_feature,
                COUNT(CASE WHEN scenario = "build" THEN 1 END) as total_build,
                COUNT(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 END) as this_month
            ', [now()->month, now()->year])->first();

            return ResponseHelper::jsonResponse(true, 'Statistics Retrieved Successfully', [
                'total_calculations' => (int) $stats->total_calculations,
                'total_value' => (float) $stats->total_value,
                'average_value' => (float) $stats->average_value,
                'total_feature' => (int) $stats->total_feature,
                'total_build' => (int) $stats->total_build,
                'this_month' => (int) $stats->this_month,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Compute totals without persisting anything -- lets the frontend
     * cross-check its own live client-side math against the authoritative
     * server calculation before the user commits to saving.
     */
    public function preview(ProjectCalculationRequest $request)
    {
        try {
            $data = $request->validated();
            $rateSetting = $this->currentRateSetting();

            $result = $this->calculatorService->calculate(
                $data['scenario'],
                $data['items'],
                $rateSetting->rate_sell_per_hour,
                $data['pm_overhead_percent'] ?? null,
                $data['infra_setup_cost'] ?? null,
                (float) $rateSetting->productive_hours_per_person * $rateSetting->team_size,
                $data['include_ppn'] ?? false,
                $data['ppn_percent'] ?? 11,
            );

            return ResponseHelper::jsonResponse(true, 'Preview Calculated Successfully', $result, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(ProjectCalculationRequest $request)
    {
        $data = $request->validated();

        try {
            $rateSetting = $this->currentRateSetting();

            $result = $this->calculatorService->calculate(
                $data['scenario'],
                $data['items'],
                $rateSetting->rate_sell_per_hour,
                $data['pm_overhead_percent'] ?? null,
                $data['infra_setup_cost'] ?? null,
                (float) $rateSetting->productive_hours_per_person * $rateSetting->team_size,
                $data['include_ppn'] ?? false,
                $data['ppn_percent'] ?? 11,
            );

            $calculation = ProjectCalculation::create([
                'name' => $data['name'],
                'client_name' => $data['client_name'] ?? null,
                'scenario' => $data['scenario'],
                'rate_cost_per_hour' => $rateSetting->rate_cost_per_hour,
                'rate_sell_per_hour' => $rateSetting->rate_sell_per_hour,
                'pm_overhead_percent' => $data['pm_overhead_percent'] ?? null,
                'infra_setup_cost' => $result['infra_setup_cost'],
                'items' => $result['items'],
                'subtotal' => $result['subtotal'],
                'buffer_total' => $result['buffer_total'],
                'pm_overhead_total' => $result['pm_overhead_total'],
                'grand_total' => $result['grand_total'],
                'include_ppn' => $data['include_ppn'] ?? false,
                'ppn_percent' => $data['ppn_percent'] ?? 11,
                'total_with_ppn' => $result['total_with_ppn'],
                'estimated_duration_weeks' => $result['estimated_duration_weeks'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Project Calculation Saved Successfully', new ProjectCalculationResource($calculation->load('creator')), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $calculation = ProjectCalculation::with('creator')->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Project Calculation Retrieved Successfully', new ProjectCalculationResource($calculation), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Calculation Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(ProjectCalculationRequest $request, int $id)
    {
        $data = $request->validated();

        try {
            $calculation = ProjectCalculation::findOrFail($id);
            $rateSetting = $this->currentRateSetting();

            $result = $this->calculatorService->calculate(
                $data['scenario'],
                $data['items'],
                $rateSetting->rate_sell_per_hour,
                $data['pm_overhead_percent'] ?? null,
                $data['infra_setup_cost'] ?? null,
                (float) $rateSetting->productive_hours_per_person * $rateSetting->team_size,
                $data['include_ppn'] ?? false,
                $data['ppn_percent'] ?? 11,
            );

            $calculation->update([
                'name' => $data['name'],
                'client_name' => $data['client_name'] ?? null,
                'scenario' => $data['scenario'],
                'rate_cost_per_hour' => $rateSetting->rate_cost_per_hour,
                'rate_sell_per_hour' => $rateSetting->rate_sell_per_hour,
                'pm_overhead_percent' => $data['pm_overhead_percent'] ?? null,
                'infra_setup_cost' => $result['infra_setup_cost'],
                'items' => $result['items'],
                'subtotal' => $result['subtotal'],
                'buffer_total' => $result['buffer_total'],
                'pm_overhead_total' => $result['pm_overhead_total'],
                'grand_total' => $result['grand_total'],
                'include_ppn' => $data['include_ppn'] ?? false,
                'ppn_percent' => $data['ppn_percent'] ?? 11,
                'total_with_ppn' => $result['total_with_ppn'],
                'estimated_duration_weeks' => $result['estimated_duration_weeks'],
                'notes' => $data['notes'] ?? null,
            ]);

            return ResponseHelper::jsonResponse(true, 'Project Calculation Updated Successfully', new ProjectCalculationResource($calculation->fresh('creator')), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Calculation Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $calculation = ProjectCalculation::findOrFail($id);
            $calculation->delete();

            return ResponseHelper::jsonResponse(true, 'Project Calculation Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Calculation Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
