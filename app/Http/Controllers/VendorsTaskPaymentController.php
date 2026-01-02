<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

use App\Models\VendorsTaskPayment;
use App\Http\Requests\VendorsTaskPaymentStoreRequest;
use App\Http\Requests\VendorsTaskPaymentUpdateRequest;
use App\Http\Resources\VendorsTaskPaymentResource;

use App\Models\VendorsTaskPivot;
use App\Http\Resources\VendorsTaskPivotResource;

use App\Http\Resources\PaginateResource;
use App\Interfaces\VendorsTaskPaymentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorsTaskPaymentController extends Controller implements HasMiddleware
{
    private VendorsTaskPaymentRepositoryInterface $vendorsTaskPaymentRepository;

    public function __construct(VendorsTaskPaymentRepositoryInterface $vendorsTaskPaymentRepository)
    {
        $this->vendorsTaskPaymentRepository = $vendorsTaskPaymentRepository;
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
            $accounts = $this->vendorsTaskPaymentRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task Payment Retrieved Successfully', VendorsTaskPaymentResource::collection($accounts), 200);
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
            $vendors = $this->vendorsTaskPaymentRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Task Payment Retrieved Successfully', PaginateResource::make($vendors, VendorsTaskPaymentResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VendorsTaskPaymentStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $task = VendorsTaskPayment::create([
                'name' => $validated['name'],
            ]);

            $pivot = VendorsTaskPivot::findOrFail($validated['pivot_id']);
            $pivot->payment_vendor_id = $task->id;
            $pivot->save();

            return ResponseHelper::jsonResponse(
                true,
                'Task Payment Created and Pivot Updated Successfully',
                [
                    'task'  => new VendorsTaskPaymentResource($task),
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
            $vendors = $this->vendorsTaskPaymentRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Payment Retrieved Successfully', new VendorsTaskPaymentResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Payment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VendorsTaskPaymentUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $task = $this->vendorsTaskPaymentRepository->update($id, $validated);

            if (!empty($validated['pivot_id'])) {
                $pivot = VendorsTaskPivot::findOrFail($validated['pivot_id']);
                $pivot->payment_vendor_id = $task->id;
                $pivot->save();
            }

            return ResponseHelper::jsonResponse(
                true,
                'Task Payment Updated Successfully',
                [
                    'task'  => new VendorsTaskPaymentResource($task),
                    'pivot' => isset($pivot) ? new VendorsTaskPivotResource($pivot) : null,
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Payment or Pivot Not Found', null, 404);
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
            $this->vendorsTaskPaymentRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Task Payment Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Task Payment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
