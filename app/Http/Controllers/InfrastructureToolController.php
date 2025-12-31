<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\InfrastructureToolStoreRequest;
use App\Http\Requests\InfrastructureToolStoreUpdateRequest;
use App\Http\Resources\InfrastructureToolResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\InfrastructureToolRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class InfrastructureToolController extends Controller implements HasMiddleware
{
    private InfrastructureToolRepositoryInterface $infrastructureToolRepository;

    public function __construct(InfrastructureToolRepositoryInterface $infrastructureToolRepository)
    {
        $this->infrastructureToolRepository = $infrastructureToolRepository;
    }

   public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['infrastructure-tool-list|infrastructure-tool-create|infrastructure-tool-edit|infrastructure-tool-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['infrastructure-tool-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['infrastructure-tool-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['infrastructure-tool-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $tools = $this->infrastructureToolRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Infrastructure Tools Retrieved Successfully', InfrastructureToolResource::collection($tools), 200);
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
            $tools = $this->infrastructureToolRepository->getAllPaginated(
                $request->search ?? null,
                $request->row_per_page
            );

            return ResponseHelper::jsonResponse(true, 'Infrastructure Tools Retrieved Successfully', PaginateResource::make($tools, InfrastructureToolResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InfrastructureToolStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $tool = $this->infrastructureToolRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Infrastructure Tool Created Successfully', new InfrastructureToolResource($tool), 201);
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
            $tool = $this->infrastructureToolRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Infrastructure Tool Retrieved Successfully', new InfrastructureToolResource($tool), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Infrastructure Tool Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InfrastructureToolStoreUpdateRequest $request, int $id)
    {
        $validated = $request->validated();

        try {
            $tool = $this->infrastructureToolRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Infrastructure Tool Updated Successfully', new InfrastructureToolResource($tool), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Infrastructure Tool Not Found', null, 404);
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
            $this->infrastructureToolRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Infrastructure Tool Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Infrastructure Tool Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}