<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

use App\Models\VendorsTaskScope;
use App\Http\Requests\VendorsTaskScopeStoreRequest;
use App\Http\Requests\VendorsTaskScopeUpdateRequest;
use App\Http\Resources\VendorsTaskScopeResource;
use App\Interfaces\VendorsTaskScopeRepositoryInterface;

use App\Models\VendorsTaskPivot;
use App\Http\Resources\VendorsTaskPivotResource;

use App\Http\Resources\PaginateResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorsTaskScopeController extends Controller implements HasMiddleware
{
    private VendorsTaskScopeRepositoryInterface $vendorsTaskScopeRepository;

    public function __construct(VendorsTaskScopeRepositoryInterface $endorsTaskScopeRepository)
    {
        $this->vendorsTaskScopeRepository = $endorsTaskScopeRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['vendors-task-scope-list|vendors-task-scope-create|vendors-task-scope-edit|vendors-task-scope-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['vendors-task-scope-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['vendors-task-scope-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['vendors-task-scope-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->vendorsTaskScopeRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task Scope Retrieved Successfully', VendorsTaskScopeResource::collection($accounts), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $vendors = $this->vendorsTaskScopeRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task Scope Retrieved Successfully', PaginateResource::make($vendors, VendorsTaskScopeResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VendorsTaskScopeStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $task = VendorsTaskScope::create([
                'name' => $validated['name'],
            ]);

            $pivot = VendorsTaskPivot::findOrFail($validated['pivot_id']);
            $pivot->scope_vendor_id = $task->id;
            $pivot->save();

            return ResponseHelper::jsonResponse(
                true,
                'Task Scope Created and Pivot Updated Successfully',
                [
                    'task'  => new VendorsTaskScopeResource($task),
                    'pivot' => new VendorsTaskPivotResource($pivot),
                ],
                201
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $vendors = $this->vendorsTaskScopeRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Scope Retrieved Successfully', new VendorsTaskScopeResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Scope Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VendorsTaskScopeUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $task = $this->vendorsTaskScopeRepository->update($id, $validated);

            if (!empty($validated['pivot_id'])) {
                $pivot = VendorsTaskPivot::findOrFail($validated['pivot_id']);
                $pivot->scope_vendor_id = $task->id;
                $pivot->save();
            }

            return ResponseHelper::jsonResponse(
                true,
                'Task Scope Updated Successfully',
                [
                    'task'  => new VendorsTaskScopeResource($task),
                    'pivot' => isset($pivot) ? new VendorsTaskPivotResource($pivot) : null,
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Scope or Pivot Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->vendorsTaskScopeRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Scope Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Scope Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
