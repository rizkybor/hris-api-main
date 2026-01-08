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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FilesCompanyController extends Controller
{
    private FilesCompanyRepositoryInterface $filesCompanyRepository;

    public function __construct(FilesCompanyRepositoryInterface $filesCompanyRepository)
    {
        $this->filesCompanyRepository = $filesCompanyRepository;
    }

    public function index(Request $request)
    {
        try {
            $files = $this->filesCompanyRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(
                true,
                'Company Files Retrieved Successfully',
                FilesCompanyResource::collection($files),
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $files = $this->filesCompanyRepository->getAllPaginated(
                $validated['search'] ?? null,
                $validated['row_per_page']
            );

            return ResponseHelper::jsonResponse(
                true,
                'Company Files Retrieved Successfully',
                PaginateResource::make($files, FilesCompanyResource::class),
                200
            );
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    public function store(FilesCompanyStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            // Log awal validasi
            Log::info('Validated data before file handling:', $validated);

            // Pastikan file ada dan valid
            if ($request->hasFile('document_path') && $request->file('document_path')->isValid()) {
                $file = $request->file('document_path');

                // Simpan file di storage/app/public/company-files
                $storedPath = $file->store('company-files', 'public');

                $validated['document_path'] = $storedPath; // relative path: company-files/nama-file.png
                $validated['type_file'] = $file->getClientMimeType();
                $validated['size_file'] = $file->getSize();

                Log::info('File uploaded:', [
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path'   => $storedPath,
                    'mime_type'     => $validated['type_file'],
                    'size'          => $validated['size_file'],
                ]);
            } else {
                Log::warning('File not received or invalid');
                return ResponseHelper::jsonResponse(false, 'File is required', null, 422);
            }

            // Log data final sebelum create
            Log::info('Data to be saved to DB:', $validated);

            // Simpan ke DB
            $fileModel = $this->filesCompanyRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Company File Created Successfully', new FilesCompanyResource($fileModel), 201);
        } catch (\Throwable $e) {
            Log::error('Error storing company file:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function update(FilesCompanyUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $fileModel = $this->filesCompanyRepository->getById($id);

            if ($request->hasFile('document_path') && $request->file('document_path')->isValid()) {
                $file = $request->file('document_path');

                // Simpan file baru
                $storedPath = $file->store('company-files', 'public');

                // Optional: hapus file lama jika ada
                if ($fileModel->document_path && Storage::disk('public')->exists($fileModel->document_path)) {
                    Storage::disk('public')->delete($fileModel->document_path);
                }

                $validated['document_path'] = $storedPath;
                $validated['type_file'] = $file->getClientMimeType();
                $validated['size_file'] = $file->getSize();

                Log::info('File updated:', [
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path'   => $storedPath,
                    'mime_type'     => $validated['type_file'],
                    'size'          => $validated['size_file'],
                ]);
            }

            // document_name tetap dari input user
            $fileModel = $this->filesCompanyRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(
                true,
                'Company File Updated Successfully',
                new FilesCompanyResource($fileModel),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('Error updating company file:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $file = $this->filesCompanyRepository->getById($id);

            // Optional: tambahkan URL lengkap untuk akses file
            if ($file->document_path) {
                $file->file_url = asset('storage/' . $file->document_path);
            }

            return ResponseHelper::jsonResponse(true, 'Company File Retrieved Successfully', new FilesCompanyResource($file), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Company File Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('Error retrieving company file:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
