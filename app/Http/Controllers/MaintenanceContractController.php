<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MaintenanceContractResource;
use App\Models\MaintenanceAdjustment;
use App\Models\MaintenanceContract;
use App\Models\MaintenancePeriod;
use App\Models\Project;
use App\Services\MaintenanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Maintenance contracts, their months (quota adjustments, what happens to
 * the leftover) and the development balance. Work logs and features live
 * in MaintenanceWorkController.
 */
class MaintenanceContractController extends Controller implements HasMiddleware
{
    public function __construct(private MaintenanceService $service) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['maintenance-menu|maintenance-list']), only: ['index', 'show', 'period']),
            new Middleware(PermissionMiddleware::using(['maintenance-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['maintenance-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['maintenance-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['maintenance-share']), only: ['share', 'unshare']),
            new Middleware(PermissionMiddleware::using(['maintenance-adjust']), only: ['setLeftoverAction', 'storeAdjustment', 'destroyAdjustment', 'adjustBalance']),
        ];
    }

    private function load(MaintenanceContract $contract, bool $detail = false): MaintenanceContract
    {
        return $contract->load([
            'client', 'project',
            'periods' => fn ($q) => $q->withSum('adjustments', 'hours')->withSum('logs', 'hours'),
            'features' => fn ($q) => $q->withSum('logs', 'hours'),
            ...($detail ? ['bankEntries.creator', 'features.logs.performer'] : []),
        ]);
    }

    public function index(Request $request)
    {
        try {
            $query = MaintenanceContract::query()
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
                ->orderBy('name');

            if ($request->search) {
                $query->search($request->search);
            }
            foreach (['status', 'client_id', 'project_id'] as $filter) {
                if ($request->filled($filter)) {
                    $query->where($filter, $request->input($filter));
                }
            }

            $contracts = $query->get();
            $contracts->each(fn ($c) => $this->service->sync($c));

            return ResponseHelper::jsonResponse(true, 'Maintenance Contracts Retrieved Successfully',
                $contracts->map(fn ($c) => new MaintenanceContractResource($this->load($c))), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $contract = MaintenanceContract::findOrFail($id);
            $this->service->sync($contract);

            return ResponseHelper::jsonResponse(true, 'Maintenance Contract Retrieved Successfully',
                (new MaintenanceContractResource($this->load($contract, true)))->withDetail(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Maintenance Contract Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'monthly_quota_hours' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(MaintenanceContract::STATUSES)],
            'leftover_policy' => ['required', Rule::in(MaintenanceContract::LEFTOVER_POLICIES)],
            'bank_cap_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'bank_expiry_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'overage_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** A project already tied to another client can't be used here. */
    private function assertProjectBelongsToClient(array $data): void
    {
        if (empty($data['project_id'])) {
            return;
        }
        $project = Project::find($data['project_id']);
        if ($project?->client_id && (int) $project->client_id !== (int) $data['client_id']) {
            throw ValidationException::withMessages(['project_id' => 'Project ini milik klien lain.']);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $this->assertProjectBelongsToClient($data);

        try {
            $contract = MaintenanceContract::create([...$data, 'created_by' => Auth::id()]);
            $this->service->sync($contract);

            return ResponseHelper::jsonResponse(true, 'Maintenance Contract Created Successfully',
                (new MaintenanceContractResource($this->load($contract->fresh(), true)))->withDetail(), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate($this->rules());
        $this->assertProjectBelongsToClient($data);

        try {
            $contract = MaintenanceContract::findOrFail($id);
            $quotaChanged = (float) $data['monthly_quota_hours'] !== $contract->monthly_quota_hours;
            $contract->update($data);

            // A new default quota applies from next month -- or to this
            // month too while nothing has been logged or adjusted yet (e.g.
            // fixing a typo right after creating the contract).
            if ($quotaChanged) {
                $current = $contract->periods()->whereNull('closed_at')
                    ->whereDate('period_month', $this->service->currentMonth().'-01')->first();
                if ($current && ! $current->logs()->exists() && ! $current->adjustments()->exists() && $current->carried_hours == 0) {
                    $current->update(['quota_hours' => $contract->monthly_quota_hours]);
                }
            }
            $this->service->sync($contract);

            return ResponseHelper::jsonResponse(true, 'Maintenance Contract Updated Successfully',
                (new MaintenanceContractResource($this->load($contract->fresh(), true)))->withDetail(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Maintenance Contract Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $contract = MaintenanceContract::findOrFail($id);
            // The client's link stops working with the contract.
            $contract->forceFill(['share_token' => null])->save();
            $contract->delete();

            return ResponseHelper::jsonResponse(true, 'Maintenance Contract Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Maintenance Contract Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    // ------------------------------------------------------------ client link

    public function share(int $id)
    {
        $contract = MaintenanceContract::findOrFail($id);
        if (! $contract->share_token) {
            $contract->forceFill(['share_token' => Str::random(48)])->save();
        }

        return ResponseHelper::jsonResponse(true, 'Client Link Ready', ['share_url' => $contract->shareUrl()], 200);
    }

    public function unshare(int $id)
    {
        MaintenanceContract::findOrFail($id)->forceFill(['share_token' => null, 'share_last_viewed_at' => null])->save();

        return ResponseHelper::jsonResponse(true, 'Client Link Revoked', ['share_url' => null], 200);
    }

    // ------------------------------------------------------------ months

    /** One month's work logs and quota adjustments. */
    public function period(int $id)
    {
        $period = MaintenancePeriod::withSum('adjustments', 'hours')->withSum('logs', 'hours')
            ->with(['adjustments' => fn ($q) => $q->with('creator')->latest('id'), 'logs' => fn ($q) => $q->with('performer')->orderByDesc('work_date')->orderByDesc('id')])
            ->findOrFail($id);

        return ResponseHelper::jsonResponse(true, 'Maintenance Period Retrieved Successfully', [
            ...MaintenanceContractResource::period($period),
            'logs' => $period->logs->map(fn ($log) => MaintenanceWorkController::logData($log)),
            'adjustments' => $period->adjustments->map(fn ($a) => [
                'id' => $a->id,
                'hours' => $a->hours,
                'reason' => $a->reason,
                'created_by' => $a->creator?->name,
                'created_at' => $a->created_at,
            ]),
        ], 200);
    }

    private function openPeriod(int $id): MaintenancePeriod
    {
        $period = MaintenancePeriod::findOrFail($id);
        if ($period->isClosed()) {
            throw ValidationException::withMessages(['period' => 'Bulan ini sudah ditutup dan tidak bisa diubah lagi.']);
        }

        return $period;
    }

    /** null = follow the contract's policy. */
    public function setLeftoverAction(Request $request, int $id)
    {
        $data = $request->validate(['leftover_action' => ['nullable', Rule::in(MaintenanceContract::LEFTOVER_POLICIES)]]);
        $period = $this->openPeriod($id);
        $period->update(['leftover_action' => $data['leftover_action'] ?? null]);

        return ResponseHelper::jsonResponse(true, 'Leftover Action Updated', MaintenanceContractResource::period($period->fresh()), 200);
    }

    public function storeAdjustment(Request $request, int $id)
    {
        $data = $request->validate([
            'hours' => ['required', 'numeric', 'between:-1000,1000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $period = $this->openPeriod($id);
        $period->adjustments()->create([...$data, 'created_by' => Auth::id()]);

        return ResponseHelper::jsonResponse(true, 'Quota Adjusted', null, 201);
    }

    public function destroyAdjustment(int $id)
    {
        $adjustment = MaintenanceAdjustment::findOrFail($id);
        $this->openPeriod($adjustment->maintenance_period_id);
        $adjustment->delete();

        return ResponseHelper::jsonResponse(true, 'Adjustment Removed', null, 200);
    }

    // ------------------------------------------------------------ balance

    /** Manual top-up (+) or deduction (-) of the development balance. */
    public function adjustBalance(Request $request, int $id)
    {
        $data = $request->validate([
            'hours' => ['required', 'numeric', 'between:-1000,1000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $contract = MaintenanceContract::findOrFail($id);

        DB::transaction(function () use ($contract, $data) {
            $contract = MaintenanceContract::whereKey($contract->id)->lockForUpdate()->first();
            $this->service->expireBalance($contract);
            $hours = round((float) $data['hours'], 2);

            if ($hours > 0) {
                $this->service->credit($contract, 'adjust_in', $hours, $this->service->expiryFrom($contract, $this->service->today()), [
                    'note' => $data['reason'],
                    'created_by' => Auth::id(),
                ]);

                return;
            }

            if (abs($hours) > $this->service->balance($contract)) {
                throw ValidationException::withMessages(['hours' => 'Saldo tidak cukup untuk dikurangi sebanyak itu.']);
            }
            $this->service->debit($contract, 'adjust_out', abs($hours), ['note' => $data['reason'], 'created_by' => Auth::id()]);
        });

        return ResponseHelper::jsonResponse(true, 'Balance Adjusted', null, 201);
    }
}
