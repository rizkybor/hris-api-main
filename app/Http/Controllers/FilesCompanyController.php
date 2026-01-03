<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\FilesCompanyStoreRequest;
use App\Http\Requests\FilesCompanyUpdateRequest;
use App\Http\Resources\FilesCompanyResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\FilesCompanyRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class FilesCompanyController extends Controller implements HasMiddleware
{
    private FilesCompanyRepositoryInterface $filesCompanyRepository;

    public function __construct(FilesCompanyRepositoryInterface $filesCompanyRepository)
    {
        $this->filesCompanyRepository = $filesCompanyRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['files-company-menu|files-company-list|files-company-create|files-company-edit|files-company-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['files-company-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['files-company-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['files-company-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $files = $this->filesCompanyRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Company Files Retrieved Successfully', FilesCompanyResource::collection($files), 200);
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
            $files = $this->filesCompanyRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Company Files Retrieved Successfully', PaginateResource::make($files, FilesCompanyResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FilesCompanyStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $file = $this->filesCompanyRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Company File Created Successfully', new FilesCompanyResource($file), 201);
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
            $file = $this->filesCompanyRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Company File Retrieved Successfully', new FilesCompanyResource($file), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FilesCompanyUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $file = $this->filesCompanyRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Company File Updated Successfully', new FilesCompanyResource($file), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
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
            $this->filesCompanyRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Company File Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}

