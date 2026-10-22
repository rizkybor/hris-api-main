<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MaintenanceContractResource;
use App\Models\MaintenanceContract;
use App\Models\MaintenanceFeature;
use App\Models\MaintenanceLog;
use App\Services\MaintenanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Hours worked for a maintenance client, and the new features paid from
 * the contract's development balance.
 */
class MaintenanceWorkController extends Controller implements HasMiddleware
{
    public function __construct(private MaintenanceService $service) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['maintenance-log']), only: ['storeLog', 'updateLog', 'destroyLog']),
            new Middleware(PermissionMiddleware::using(['maintenance-edit']), only: ['storeFeature', 'updateFeature', 'destroyFeature', 'approveFeature', 'completeFeature', 'cancelFeature']),
        ];
    }

    public static function logData(MaintenanceLog $log): array
    {
        return [
            'id' => $log->id,
            'maintenance_period_id' => $log->maintenance_period_id,
            'maintenance_feature_id' => $log->maintenance_feature_id,
            'work_date' => $log->work_date?->toDateString(),
            'title' => $log->title,
            'description' => $log->description,
            'hours' => $log->hours,
            'category' => $log->category,
            'performed_by' => $log->performed_by,
            'performer' => $log->performer?->name,
            'visible_to_client' => $log->visible_to_client,
            'created_at' => $log->created_at,
        ];
    }

    // ------------------------------------------------------------ work logs

    private function logRules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'category' => ['nullable', Rule::in(MaintenanceLog::CATEGORIES)],
            'performed_by' => ['nullable', 'integer', 'exists:users,id'],
            'visible_to_client' => ['boolean'],
            'maintenance_feature_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Where the hours go: an open feature of this contract, or the month of
     * work_date (which must exist and still be open).
     */
    private function target(MaintenanceContract $contract, array $data): array
    {
        $date = Carbon::parse($data['work_date'], MaintenanceService::TZ);
        if ($date->gt($this->service->today()->endOfDay())) {
            throw ValidationException::withMessages(['work_date' => 'Tanggal pekerjaan tidak boleh di masa depan.']);
        }

        if (! empty($data['maintenance_feature_id'])) {
            $feature = $contract->features()->find($data['maintenance_feature_id']);
            if (! $feature || ! $feature->isOpen()) {
                throw ValidationException::withMessages(['maintenance_feature_id' => 'Jam hanya bisa dicatat ke fitur yang sudah disetujui dan belum selesai.']);
            }

            return ['maintenance_period_id' => null, 'maintenance_feature_id' => $feature->id];
        }

        $period = $this->service->periodFor($contract, $date);
        if (! $period) {
            throw ValidationException::withMessages(['work_date' => 'Kontrak tidak berjalan pada bulan tersebut.']);
        }
        if ($period->isClosed()) {
            throw ValidationException::withMessages(['work_date' => 'Bulan '.$period->period_month->locale('id')->translatedFormat('F Y').' sudah ditutup.']);
        }

        return ['maintenance_period_id' => $period->id, 'maintenance_feature_id' => null];
    }

    /** A log can only change while its month or feature is still open. */
    private function assertEditable(MaintenanceLog $log): void
    {
        if ($log->maintenance_feature_id && ! $log->feature?->isOpen()) {
            throw ValidationException::withMessages(['log' => 'Fitur ini sudah selesai, catatannya tidak bisa diubah.']);
        }
        if ($log->maintenance_period_id && $log->period?->isClosed()) {
            throw ValidationException::withMessages(['log' => 'Bulan ini sudah ditutup, catatannya tidak bisa diubah.']);
        }
    }

    public function storeLog(Request $request, int $contractId)
    {
        $data = $request->validate($this->logRules());
        $contract = MaintenanceContract::findOrFail($contractId);
        $this->service->sync($contract);

        $log = DB::transaction(function () use ($contract, $data) {
            $log = $contract->logs()->create([
                ...$data,
                ...$this->target($contract, $data),
                'visible_to_client' => $data['visible_to_client'] ?? true,
                'created_by' => Auth::id(),
            ]);
            if ($log->feature?->status === 'approved') {
                $log->feature->update(['status' => 'in_progress']);
            }

            return $log;
        });

        return ResponseHelper::jsonResponse(true, 'Work Logged', self::logData($log->load('performer')), 201);
    }

    public function updateLog(Request $request, int $id)
    {
        $data = $request->validate($this->logRules());
        $log = MaintenanceLog::with(['contract', 'feature', 'period'])->findOrFail($id);
        $this->assertEditable($log);

        $log->update([...$data, ...$this->target($log->contract, $data), 'visible_to_client' => $data['visible_to_client'] ?? $log->visible_to_client]);

        return ResponseHelper::jsonResponse(true, 'Work Log Updated', self::logData($log->fresh('performer')), 200);
    }

    public function destroyLog(int $id)
    {
        $log = MaintenanceLog::with(['feature', 'period'])->findOrFail($id);
        $this->assertEditable($log);
        $log->delete();

        return ResponseHelper::jsonResponse(true, 'Work Log Deleted', null, 200);
    }

    // ------------------------------------------------------------ features

    public function storeFeature(Request $request, int $contractId)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'estimated_hours' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'visible_to_client' => ['boolean'],
        ]);
        $contract = MaintenanceContract::findOrFail($contractId);
        $feature = $contract->features()->create([...$data, 'status' => 'proposed', 'created_by' => Auth::id()]);

        return ResponseHelper::jsonResponse(true, 'Feature Added', MaintenanceContractResource::feature($feature), 201);
    }

    /** The estimate is fixed once approved (it's what was reserved). */
    public function updateFeature(Request $request, int $id)
    {
        $feature = MaintenanceFeature::findOrFail($id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'estimated_hours' => [$feature->status === 'proposed' ? 'required' : 'prohibited', 'numeric', 'min:0.5', 'max:1000'],
            'visible_to_client' => ['boolean'],
        ]);
        $feature->update($data);

        return ResponseHelper::jsonResponse(true, 'Feature Updated', MaintenanceContractResource::feature($feature->fresh()), 200);
    }

    public function destroyFeature(int $id)
    {
        $feature = MaintenanceFeature::findOrFail($id);
        if ($feature->status !== 'proposed') {
            throw ValidationException::withMessages(['feature' => 'Hanya fitur berstatus Diusulkan yang bisa dihapus. Batalkan fitur ini sebagai gantinya.']);
        }
        $feature->delete();

        return ResponseHelper::jsonResponse(true, 'Feature Deleted', null, 200);
    }

    public function approveFeature(int $id)
    {
        $feature = MaintenanceFeature::findOrFail($id);
        if ($feature->status !== 'proposed') {
            throw ValidationException::withMessages(['feature' => 'Fitur ini sudah disetujui sebelumnya.']);
        }
        $this->service->approveFeature($feature);

        return ResponseHelper::jsonResponse(true, 'Feature Approved', MaintenanceContractResource::feature($feature->fresh()), 200);
    }

    public function completeFeature(int $id)
    {
        $feature = MaintenanceFeature::findOrFail($id);
        if (! $feature->isOpen()) {
            throw ValidationException::withMessages(['feature' => 'Fitur ini belum disetujui atau sudah selesai.']);
        }
        $this->service->settleFeature($feature, 'done');

        return ResponseHelper::jsonResponse(true, 'Feature Completed', MaintenanceContractResource::feature($feature->fresh()), 200);
    }

    public function cancelFeature(int $id)
    {
        $feature = MaintenanceFeature::findOrFail($id);
        if ($feature->isSettled()) {
            throw ValidationException::withMessages(['feature' => 'Fitur ini sudah selesai atau dibatalkan.']);
        }

        if ($feature->status === 'proposed') {
            $feature->update(['status' => 'cancelled', 'completed_at' => now()]);
        } else {
            // Hours already logged stay spent; the rest of the reservation
            // goes back to the balance.
            $this->service->settleFeature($feature, 'cancelled');
        }

        return ResponseHelper::jsonResponse(true, 'Feature Cancelled', MaintenanceContractResource::feature($feature->fresh()), 200);
    }
}
