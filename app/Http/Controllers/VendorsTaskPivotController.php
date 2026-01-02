<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\VendorsTaskPivotStoreRequest;
use App\Http\Requests\VendorsTaskPivotUpdateRequest;
use App\Http\Resources\VendorsTaskPivotResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\VendorsTaskPivotRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorsTaskPivotController extends Controller implements HasMiddleware
{
    private VendorsTaskPivotRepositoryInterface $vendorsTaskPivotRepository;

    public function __construct(VendorsTaskPivotRepositoryInterface $vendorsTaskPivotRepository)
    {
        $this->vendorsTaskPivotRepository = $vendorsTaskPivotRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['vendors-task-pivot-list|vendors-task-pivot-create|vendors-task-pivot-edit|vendors-task-pivot-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['vendors-task-pivot-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['vendors-task-pivot-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['vendors-task-pivot-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->vendorsTaskPivotRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task Pivot Retrieved Successfully', VendorsTaskPivotResource::collection($accounts), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $vendors = $this->vendorsTaskPivotRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task Pivot Retrieved Successfully', PaginateResource::make($vendors, VendorsTaskPivotResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VendorsTaskPivotStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $vendors = $this->vendorsTaskPivotRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Pivot Created Successfully', new VendorsTaskPivotResource($vendors), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $vendors = $this->vendorsTaskPivotRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Pivot Retrieved Successfully', new VendorsTaskPivotResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Pivot Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VendorsTaskPivotUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $vendors = $this->vendorsTaskPivotRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Pivot Updated Successfully', new VendorsTaskPivotResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Pivot Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->vendorsTaskPivotRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Pivot Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Pivot Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}

