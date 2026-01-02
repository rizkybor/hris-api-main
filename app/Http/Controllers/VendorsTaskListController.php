<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

use App\Models\VendorsTaskList;
use App\Http\Requests\VendorsTaskListStoreRequest;
use App\Http\Requests\VendorsTaskListUpdateRequest;
use App\Http\Resources\VendorsTaskListResource;

use App\Models\VendorsTaskPivot;
use App\Http\Resources\VendorsTaskPivotResource;

use App\Http\Resources\PaginateResource;
use App\Interfaces\VendorsTaskListRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorsTaskListController extends Controller implements HasMiddleware
{
    private VendorsTaskListRepositoryInterface $vendorsTaskListRepository;

    public function __construct(VendorsTaskListRepositoryInterface $vendorsTaskListRepository)
    {
        $this->vendorsTaskListRepository = $vendorsTaskListRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['credential-account-list|credential-account-create|credential-account-edit|credential-account-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['credential-account-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['credential-account-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['credential-account-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->vendorsTaskListRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task List Retrieved Successfully', VendorsTaskListResource::collection($accounts), 200);
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
            $vendors = $this->vendorsTaskListRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task List Retrieved Successfully', PaginateResource::make($vendors, VendorsTaskListResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VendorsTaskListStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $task = VendorsTaskList::create([
                'name' => $validated['name'],
            ]);

            $pivot = VendorsTaskPivot::findOrFail($validated['pivot_id']);
            $pivot->task_vendor_id = $task->id;
            $pivot->save();

            return ResponseHelper::jsonResponse(
                true,
                'Task List Created and Pivot Updated Successfully',
                [
                    'task'  => new VendorsTaskListResource($task),
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
            $vendors = $this->vendorsTaskListRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Account Retrieved Successfully', new VendorsTaskListResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(VendorsTaskListUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $task = $this->vendorsTaskListRepository->update($id, $validated);

            if (!empty($validated['pivot_id'])) {
                $pivot = VendorsTaskPivot::findOrFail($validated['pivot_id']);
                $pivot->task_vendor_id = $task->id;
                $pivot->save();
            }

            return ResponseHelper::jsonResponse(
                true,
                'Task List Updated Successfully',
                [
                    'task'  => new VendorsTaskListResource($task),
                    'pivot' => isset($pivot) ? new VendorsTaskPivotResource($pivot) : null,
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task List or Pivot Not Found', null, 404);
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
            $this->vendorsTaskListRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Account Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
