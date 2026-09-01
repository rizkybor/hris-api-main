<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\VendorEvaluationStoreRequest;
use App\Http\Resources\VendorEvaluationResource;
use App\Interfaces\VendorEvaluationRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorEvaluationController extends Controller implements HasMiddleware
{
    public function __construct(private VendorEvaluationRepositoryInterface $vendorEvaluationRepository) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['vendors-evaluation-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['vendors-evaluation-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['vendors-evaluation-delete']), only: ['destroy']),
        ];
    }

    /**
     * List evaluations for one vendor.
     */
    public function index(string $vendorId)
    {
        try {
            $evaluations = $this->vendorEvaluationRepository->getByVendor($vendorId);

            return ResponseHelper::jsonResponse(true, 'Vendor Evaluations Retrieved Successfully', VendorEvaluationResource::collection($evaluations), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(VendorEvaluationStoreRequest $request)
    {
        $data = $request->validated();
        $data['evaluated_by'] = Auth::id();

        try {
            $evaluation = $this->vendorEvaluationRepository->create($data);

            return ResponseHelper::jsonResponse(true, 'Vendor Evaluation Created Successfully', new VendorEvaluationResource($evaluation), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $this->vendorEvaluationRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendor Evaluation Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendor Evaluation Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
