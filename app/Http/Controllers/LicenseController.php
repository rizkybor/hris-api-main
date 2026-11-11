<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LicenseStoreRequest;
use App\Http\Requests\LicenseUpdateRequest;
use App\Http\Resources\LicenseEventResource;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\RecentLicenseEventResource;
use App\Interfaces\LicenseRepositoryInterface;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseProduct;
use App\Repositories\LicenseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * License Manager admin API. Every route is nested under one product
 * (/license-manager/products/{product}/licenses/...), {product} being the
 * product code; a license of another product answers 404.
 */
class LicenseController extends Controller implements HasMiddleware
{
    public function __construct(private LicenseRepositoryInterface $licenseRepository) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['license-manager-list']), only: ['index', 'getAllPaginated', 'summary', 'overview']),
            // The edit page loads the license too, so edit may read one.
            new Middleware(PermissionMiddleware::using(['license-manager-view|license-manager-edit']), only: ['show']),
            new Middleware(PermissionMiddleware::using(['license-manager-view']), only: ['events', 'expiring', 'recentEvents']),
            new Middleware(PermissionMiddleware::using(['license-manager-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['license-manager-edit']), only: ['update', 'releaseActivation']),
            new Middleware(PermissionMiddleware::using(['license-manager-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $product)
    {
        $request->validate($this->filterRules());

        try {
            $licenses = $this->licenseRepository->getAll(
                LicenseProduct::findByCodeOrFail($product),
                $request->search,
                $request->status,
                $request->type,
                $request->plan,
                $request->limit,
                true,
                $request->sort_by,
                $request->sort_dir,
                $request->platform
            );

            return ResponseHelper::jsonResponse(true, 'Licenses Retrieved Successfully', LicenseResource::collection($licenses), 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request, string $product)
    {
        $request = $request->validate(array_merge($this->filterRules(), [
            'row_per_page' => 'nullable|integer|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]));

        try {
            $licenses = $this->licenseRepository->getAllPaginated(
                LicenseProduct::findByCodeOrFail($product),
                $request['search'] ?? null,
                $request['status'] ?? null,
                $request['type'] ?? null,
                $request['plan'] ?? null,
                (int) ($request['per_page'] ?? $request['row_per_page'] ?? 10),
                $request['sort_by'] ?? null,
                $request['sort_dir'] ?? null,
                $request['platform'] ?? null
            );

            return ResponseHelper::jsonResponse(true, 'Licenses Retrieved Successfully', PaginateResource::make($licenses, LicenseResource::class), 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage. The key is generated
     * server-side with the product's key prefix.
     */
    public function store(LicenseStoreRequest $request, string $product)
    {
        $data = $request->validated();

        try {
            $license = $this->licenseRepository->create(LicenseProduct::findByCodeOrFail($product), $data);

            return ResponseHelper::jsonResponse(true, 'License Created Successfully', new LicenseResource($license), 201);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (ValidationException $e) {
            // E.g. the product reached the highest key number (9999): 422.
            throw $e;
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource, with its activated devices.
     */
    public function show(string $product, string $license)
    {
        try {
            $license = $this->licenseRepository->getById(LicenseProduct::findByCodeOrFail($product), $license);

            return ResponseHelper::jsonResponse(true, 'License Retrieved Successfully', new LicenseResource($license), 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage (the key is immutable).
     */
    public function update(LicenseUpdateRequest $request, string $product, string $license)
    {
        $data = $request->validated();

        try {
            $license = $this->licenseRepository->update(LicenseProduct::findByCodeOrFail($product), $license, $data);

            return ResponseHelper::jsonResponse(true, 'License Updated Successfully', new LicenseResource($license), 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (ValidationException $e) {
            // E.g. Max devices below the per-device keys already used: 422.
            throw $e;
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $product, string $license)
    {
        try {
            $this->licenseRepository->delete(LicenseProduct::findByCodeOrFail($product), $license);

            return ResponseHelper::jsonResponse(true, 'License Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Release one device so its slot can be used by another install.
     */
    public function releaseActivation(string $product, string $license, string $activation)
    {
        try {
            $productModel = LicenseProduct::findByCodeOrFail($product);
            $this->licenseRepository->releaseActivation($productModel, $license, $activation);

            return ResponseHelper::jsonResponse(true, 'Device Released Successfully', new LicenseResource($this->licenseRepository->getById($productModel, $license)), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'License or Device Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Counts for one product's dashboard cards.
     */
    public function summary(string $product)
    {
        try {
            return ResponseHelper::jsonResponse(true, 'License Summary Retrieved Successfully', $this->licenseRepository->summary(LicenseProduct::findByCodeOrFail($product)), 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Counts per product (and all together) for the overview page.
     */
    public function overview()
    {
        try {
            return ResponseHelper::jsonResponse(true, 'License Overview Retrieved Successfully', $this->licenseRepository->overview(), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Active licenses of every product expiring within the next N days,
     * soonest first (overview widget).
     */
    public function expiring(Request $request)
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:3650',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $licenses = $this->licenseRepository->expiring((int) ($data['days'] ?? 30), (int) ($data['limit'] ?? 10));

            return ResponseHelper::jsonResponse(true, 'Expiring Licenses Retrieved Successfully', LicenseResource::collection($licenses), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Latest tracking events across all products, newest first (overview
     * widget).
     */
    public function recentEvents(Request $request)
    {
        $data = $request->validate(['limit' => 'nullable|integer|min:1|max:100']);

        try {
            $events = $this->licenseRepository->recentEvents((int) ($data['limit'] ?? 15));

            return ResponseHelper::jsonResponse(true, 'Recent License Events Retrieved Successfully', RecentLicenseEventResource::collection($events), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Tracking timeline of one license, newest first.
     */
    public function events(Request $request, string $product, string $license)
    {
        $request = $request->validate([
            'event' => ['nullable', Rule::in(LicenseEvent::EVENTS)],
            'row_per_page' => 'nullable|integer|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $events = $this->licenseRepository->getEventsPaginated(
                LicenseProduct::findByCodeOrFail($product),
                $license,
                $request['event'] ?? null,
                (int) ($request['per_page'] ?? $request['row_per_page'] ?? 15)
            );

            return ResponseHelper::jsonResponse(true, 'License Events Retrieved Successfully', PaginateResource::make($events, LicenseEventResource::class), 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFound();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function notFound()
    {
        return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
    }

    private function filterRules(): array
    {
        return [
            'sort_by' => ['nullable', Rule::in(LicenseRepository::SORT_FIELDS)],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'search' => 'nullable|string',
            'status' => ['nullable', Rule::in(License::STATUSES)],
            'type' => ['nullable', Rule::in(License::TYPES)],
            'plan' => ['nullable', Rule::in(License::PLANS)],
            'platform' => ['nullable', Rule::in(License::PLATFORMS)],
        ];
    }
}
