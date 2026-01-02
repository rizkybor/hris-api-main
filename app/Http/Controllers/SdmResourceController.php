<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SdmResourceStoreRequest;
use App\Http\Requests\SdmResourceStoreUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\SdmResource;
use App\Interfaces\SdmResourceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class SdmResourceController extends Controller implements HasMiddleware
{
    private SdmResourceRepositoryInterface $sdmResourceRepository;

    public function __construct(SdmResourceRepositoryInterface $sdmResourceRepository)
    {
        $this->sdmResourceRepository = $sdmResourceRepository;
    }

   public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['sdm-resource-list|sdm-resource-create|sdm-resource-edit|sdm-resource-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['sdm-resource-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['sdm-resource-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['sdm-resource-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $resources = $this->sdmResourceRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'SDM Resources Retrieved Successfully', SdmResource::collection($resources), 200);
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
            $resources = $this->sdmResourceRepository->getAllPaginated(
                $request->search ?? null,
                $request->row_per_page
            );

            return ResponseHelper::jsonResponse(true, 'SDM Resources Retrieved Successfully', PaginateResource::make($resources, SdmResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

        /**
     * Store a newly created resource in storage.
     */
    public function store(SdmResourceStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $resource = $this->sdmResourceRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'SDM Resource Created Successfully', new SdmResource($resource), 201);
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
            $resource = $this->sdmResourceRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'SDM Resource Retrieved Successfully', new SdmResource($resource), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'SDM Resource Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SdmResourceStoreUpdateRequest $request, int $id)
    {
        $validated = $request->validated();

        try {
            $resource = $this->sdmResourceRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'SDM Resource Updated Successfully', new SdmResource($resource), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'SDM Resource Not Found', null, 404);
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
            $this->sdmResourceRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'SDM Resource Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'SDM Resource Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}