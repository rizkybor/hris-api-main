<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SupplierStoreRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Deliberately minimal (no menu/edit) -- exists only to back the "quick
 * add" supplier picker on the Company Asset form, not as its own managed
 * module. See app/Models/Supplier.php for why this isn't just Client.
 */
class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['asset-list|asset-create']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['asset-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['asset-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Supplier::query()->orderBy('name');

            if ($request->search) {
                $query->search($request->search);
            }

            return ResponseHelper::jsonResponse(true, 'Suppliers Retrieved Successfully', SupplierResource::collection($query->get()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(SupplierStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $supplier = Supplier::create($validated);

            return ResponseHelper::jsonResponse(true, 'Supplier Created Successfully', new SupplierResource($supplier), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $supplier->delete();

            return ResponseHelper::jsonResponse(true, 'Supplier Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Supplier Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
