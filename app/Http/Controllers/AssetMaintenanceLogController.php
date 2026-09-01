<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\AssetMaintenanceLogResource;
use App\Models\AssetMaintenanceLog;
use App\Models\CompanyAsset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AssetMaintenanceLogController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['asset-maintenance-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['asset-maintenance-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['asset-maintenance-delete']), only: ['destroy']),
        ];
    }

    /**
     * Maintenance history for one asset.
     */
    public function index(string $assetId)
    {
        try {
            CompanyAsset::findOrFail($assetId);

            $logs = AssetMaintenanceLog::with('performer')
                ->where('asset_id', $assetId)
                ->orderByDesc('performed_at')
                ->get();

            return ResponseHelper::jsonResponse(true, 'Maintenance Logs Retrieved Successfully', AssetMaintenanceLogResource::collection($logs), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Asset Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Logs a maintenance/service event and, when next_due_date is given,
     * caches it onto the asset's own next_maintenance_due_date so
     * "due soon" lists don't need to join asset_maintenance_logs.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:company_assets,id',
            'performed_at' => 'required|date',
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
            'next_due_date' => 'nullable|date|after_or_equal:performed_at',
        ]);

        try {
            $log = DB::transaction(function () use ($validated, $request) {
                $log = AssetMaintenanceLog::create([
                    ...$validated,
                    'performed_by' => $request->user()->id,
                ]);

                CompanyAsset::where('id', $validated['asset_id'])
                    ->update(['next_maintenance_due_date' => $validated['next_due_date'] ?? null]);

                return $log;
            });

            return ResponseHelper::jsonResponse(true, 'Maintenance Log Created Successfully', new AssetMaintenanceLogResource($log->load('performer')), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $log = AssetMaintenanceLog::findOrFail($id);
            $log->delete();

            // Recompute the cached next_maintenance_due_date from whatever
            // log entry (if any) is now the most recent for this asset.
            $latestNextDue = AssetMaintenanceLog::where('asset_id', $log->asset_id)
                ->whereNotNull('next_due_date')
                ->orderByDesc('performed_at')
                ->value('next_due_date');

            CompanyAsset::where('id', $log->asset_id)->update(['next_maintenance_due_date' => $latestNextDue]);

            return ResponseHelper::jsonResponse(true, 'Maintenance Log Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Maintenance Log Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
