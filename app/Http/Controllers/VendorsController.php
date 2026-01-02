<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\VendorsStoreRequest;
use App\Http\Requests\VendorsUpdateRequest;
use App\Http\Resources\VendorsResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\VendorsRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorsController extends Controller implements HasMiddleware
{
    private VendorsRepositoryInterface $vendorsRepository;

    public function __construct(VendorsRepositoryInterface $vendorsRepository)
    {
        $this->vendorsRepository = $vendorsRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['vendors-list|vendors-create|vendors-edit|vendors-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['vendors-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['vendors-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['vendors-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $vendors = $this->vendorsRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            // Eager load relasi agar taskPivots & attachments muncul
            $vendors->load([
                'taskPivots.taskVendor',
                'taskPivots.paymentVendor',
                'taskPivots.scopeVendor',
                'attachments'
            ]);

            return ResponseHelper::jsonResponse(
                true,
                'Vendors Retrieved Successfully',
                VendorsResource::collection($vendors),
                200
            );
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
            $vendors = $this->vendorsRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Retrieved Successfully', PaginateResource::make($vendors, VendorsResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VendorsStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $vendors = $this->vendorsRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Vendors Created Successfully', new VendorsResource($vendors), 201);
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
            $vendor = $this->vendorsRepository->getById($id);

            // Eager load relasi
            $vendor->load([
                'taskPivots.taskVendor',
                'taskPivots.paymentVendor',
                'taskPivots.scopeVendor',
                'attachments'
            ]);

            return ResponseHelper::jsonResponse(
                true,
                'Vendors Account Retrieved Successfully',
                new VendorsResource($vendor),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(VendorsUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $vendors = $this->vendorsRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Vendors Account Updated Successfully', new VendorsResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Account Not Found', null, 404);
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
            $this->vendorsRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Account Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
