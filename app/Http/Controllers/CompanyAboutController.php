<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\CompanyAboutStoreRequest;
use App\Http\Requests\CompanyAboutUpdateRequest;
use App\Http\Resources\CompanyAboutResource;
use App\Interfaces\CompanyAboutRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CompanyAboutController extends Controller implements HasMiddleware
{
    private CompanyAboutRepositoryInterface $companyAboutRepository;

    public function __construct(CompanyAboutRepositoryInterface $companyAboutRepository)
    {
        $this->companyAboutRepository = $companyAboutRepository;
    }

   public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['company-about-menu|company-about-create|company-about-edit|company-about-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['company-about-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['company-about-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['company-about-delete']), only: ['destroy']),
        ];
    }



    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $companyAbout = $this->companyAboutRepository->getAll();

            return ResponseHelper::jsonResponse(true, 'Company About Retrieved Successfully', CompanyAboutResource::collection($companyAbout), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CompanyAboutStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $finance = $this->companyAboutRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Company About Created Successfully', new CompanyAboutResource($finance), 201);
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
            $companyAbout = $this->companyAboutRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Company About Retrieved Successfully', new CompanyAboutResource($companyAbout), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company About Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CompanyAboutUpdateRequest $request, int $id)
    {
        $validated = $request->validated();

        try {
            $finance = $this->companyAboutRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Company About Updated Successfully', new CompanyAboutResource($finance), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company About Not Found', null, 404);
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
            $this->companyAboutRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Company About Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company About Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
