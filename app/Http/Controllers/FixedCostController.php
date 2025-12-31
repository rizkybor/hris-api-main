<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\FixedCostStoreRequest;
use App\Http\Requests\FixedCostStoreUpdateRequest;
use App\Http\Resources\FixedCostResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\FixedCostRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class FixedCostController extends Controller implements HasMiddleware
{
    private FixedCostRepositoryInterface $fixedCostRepository;

    public function __construct(FixedCostRepositoryInterface $fixedCostRepository)
    {
        $this->fixedCostRepository = $fixedCostRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['fixed-cost-list|fixed-cost-create|fixed-cost-edit|fixed-cost-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['fixed-cost-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['fixed-cost-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['fixed-cost-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $costs = $this->fixedCostRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Fixed Costs Retrieved Successfully', FixedCostResource::collection($costs), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $costs = $this->fixedCostRepository->getAllPaginated(
                $request->search ?? null,
                $request->row_per_page
            );

            return ResponseHelper::jsonResponse(true, 'Fixed Costs Retrieved Successfully', PaginateResource::make($costs, FixedCostResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FixedCostStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $cost = $this->fixedCostRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Fixed Cost Created Successfully', new FixedCostResource($cost), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $cost = $this->fixedCostRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Fixed Cost Retrieved Successfully', new FixedCostResource($cost), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Fixed Cost Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FixedCostStoreUpdateRequest $request, int $id)
    {
        $validated = $request->validated();

        try {
            $cost = $this->fixedCostRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Fixed Cost Updated Successfully', new FixedCostResource($cost), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Fixed Cost Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $this->fixedCostRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Fixed Cost Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Fixed Cost Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}