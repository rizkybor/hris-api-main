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
use Illuminate\Support\Facades\Storage;
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
            $files = $this->filesCompanyRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Company Files Retrieved Successfully', PaginateResource::make($files, FilesCompanyResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function statistics()
    {
        try {
            $stats = $this->filesCompanyRepository->statistics();

            return ResponseHelper::jsonResponse(
                true,
                'Company Files Statistics Retrieved Successfully',
                $stats,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Internal Server Error: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    public function store(FilesCompanyStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            // Jika ada file, simpan dulu di disk private
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');
                $validated['document_path'] = $file->store('', 'company_files');
                $validated['document_name'] = $file->getClientOriginalName();
                $validated['type_file'] = $file->getClientMimeType();
                $validated['size_file'] = $file->getSize();
            }

            // Kirim array data ke repository, repository yang buat DTO
            $fileModel = $this->filesCompanyRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Company File Created Successfully', new FilesCompanyResource($fileModel), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $file = $this->filesCompanyRepository->getById($id);
            return ResponseHelper::jsonResponse(true, 'Company File Retrieved Successfully', new FilesCompanyResource($file), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function update(FilesCompanyUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            // Jika ada file baru, simpan di disk
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $file = $request->file('file');
                $validated['document_path'] = $file->store('', 'company_files');
                $validated['document_name'] = $file->getClientOriginalName();
                $validated['type_file'] = $file->getClientMimeType();
                $validated['size_file'] = $file->getSize();
            }

            // Kirim array data ke repository
            $fileModel = $this->filesCompanyRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Company File Updated Successfully', new FilesCompanyResource($fileModel), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $this->filesCompanyRepository->delete($id);
            return ResponseHelper::jsonResponse(true, 'Company File Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
