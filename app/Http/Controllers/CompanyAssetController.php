<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CompanyAssetResource;
use App\Http\Resources\PaginateResource;
use App\Models\AssetAssignmentHistory;
use App\Models\CompanyAsset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CompanyAssetController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['asset-list']), only: ['index', 'show', 'statistics']),
            new Middleware(PermissionMiddleware::using(['asset-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['asset-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['asset-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['asset-assign']), only: ['assign']),
            new Middleware(PermissionMiddleware::using(['asset-assign']), only: ['returnAsset']),
            new Middleware(PermissionMiddleware::using(['asset-my-assets']), only: ['myAssets']),
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'category' => 'nullable|string',
            'status' => 'nullable|string',
            'row_per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        try {
            $assets = CompanyAsset::with('assignee.user')
                ->when($validated['search'] ?? null, function ($q, $search) {
                    $q->where(function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('asset_code', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%");
                    });
                })
                ->when($validated['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
                ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->latest('created_at')
                ->paginate($validated['row_per_page'] ?? 10, ['*'], 'page', $validated['page'] ?? 1);

            return ResponseHelper::jsonResponse(true, 'Assets Retrieved Successfully', PaginateResource::make($assets, CompanyAssetResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function statistics()
    {
        try {
            $byStatus = CompanyAsset::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $byCategory = CompanyAsset::selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');

            return ResponseHelper::jsonResponse(true, 'Asset Statistics Retrieved Successfully', [
                'total' => CompanyAsset::count(),
                'available' => (int) ($byStatus['available'] ?? 0),
                'assigned' => (int) ($byStatus['assigned'] ?? 0),
                'maintenance' => (int) ($byStatus['maintenance'] ?? 0),
                'total_value' => (float) CompanyAsset::sum('purchase_price'),
                'by_category' => $byCategory,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $asset = CompanyAsset::with(['assignee.user', 'assignmentHistories.employee.user', 'assignmentHistories.assignedBy'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Asset Retrieved Successfully', new CompanyAssetResource($asset), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Asset Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_code' => 'required|string|max:100|unique:company_assets,asset_code',
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:laptop,phone,vehicle,furniture,other',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'condition' => 'nullable|string|in:good,fair,damaged',
            'notes' => 'nullable|string',
        ]);

        try {
            $asset = CompanyAsset::create([
                ...$validated,
                'condition' => $validated['condition'] ?? 'good',
                'status' => 'available',
            ]);

            return ResponseHelper::jsonResponse(true, 'Asset Created Successfully', new CompanyAssetResource($asset), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'asset_code' => 'sometimes|required|string|max:100|unique:company_assets,asset_code,'.$id,
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|in:laptop,phone,vehicle,furniture,other',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'condition' => 'nullable|string|in:good,fair,damaged',
            'status' => 'nullable|string|in:available,assigned,maintenance,retired,lost',
            'notes' => 'nullable|string',
        ]);

        try {
            $asset = CompanyAsset::findOrFail($id);
            $asset->update($validated);

            return ResponseHelper::jsonResponse(true, 'Asset Updated Successfully', new CompanyAssetResource($asset), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Asset Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $asset = CompanyAsset::findOrFail($id);
            $asset->delete();

            return ResponseHelper::jsonResponse(true, 'Asset Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Asset Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function assign(Request $request, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employee_profiles,id',
            'condition_at_assignment' => 'nullable|string|in:good,fair,damaged',
            'notes' => 'nullable|string',
        ]);

        try {
            $asset = DB::transaction(function () use ($validated, $id, $request) {
                $asset = CompanyAsset::findOrFail($id);

                if ($asset->status === 'assigned') {
                    throw new \Exception('This asset is currently assigned to another employee. Return it first before assigning to a new employee.');
                }

                $condition = $validated['condition_at_assignment'] ?? $asset->condition;

                AssetAssignmentHistory::create([
                    'asset_id' => $asset->id,
                    'employee_id' => $validated['employee_id'],
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now()->toDateString(),
                    'condition_at_assignment' => $condition,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $asset->update([
                    'status' => 'assigned',
                    'assigned_to' => $validated['employee_id'],
                    'assigned_at' => now()->toDateString(),
                    'condition' => $condition,
                ]);

                return $asset;
            });

            return ResponseHelper::jsonResponse(true, 'Asset Assigned Successfully', new CompanyAssetResource($asset->fresh('assignee.user')), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Asset Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function returnAsset(Request $request, string $id)
    {
        $validated = $request->validate([
            'condition_at_return' => 'nullable|string|in:good,fair,damaged',
            'notes' => 'nullable|string',
        ]);

        try {
            $asset = DB::transaction(function () use ($validated, $id) {
                $asset = CompanyAsset::findOrFail($id);

                if ($asset->status !== 'assigned' || ! $asset->assigned_to) {
                    throw new \Exception('This asset is not currently assigned to anyone.');
                }

                $condition = $validated['condition_at_return'] ?? $asset->condition;

                AssetAssignmentHistory::where('asset_id', $asset->id)
                    ->where('employee_id', $asset->assigned_to)
                    ->whereNull('returned_at')
                    ->latest('assigned_at')
                    ->first()
                    ?->update([
                        'returned_at' => now()->toDateString(),
                        'condition_at_return' => $condition,
                        'notes' => $validated['notes'] ?? null,
                    ]);

                $asset->update([
                    'status' => 'available',
                    'assigned_to' => null,
                    'assigned_at' => null,
                    'condition' => $condition,
                ]);

                return $asset;
            });

            return ResponseHelper::jsonResponse(true, 'Asset Returned Successfully', new CompanyAssetResource($asset->fresh()), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Asset Not Found', null, 404);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Assets currently assigned to the authenticated employee.
     */
    public function myAssets(Request $request)
    {
        try {
            $employeeId = $request->user()->employeeProfile?->id;

            $assets = CompanyAsset::where('assigned_to', $employeeId)->get();

            return ResponseHelper::jsonResponse(true, 'My Assets Retrieved Successfully', CompanyAssetResource::collection($assets), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
