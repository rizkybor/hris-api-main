<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\VendorsAttachmentStoreRequest;
use App\Http\Requests\VendorsAttachmentUpdateRequest;
use App\Http\Resources\VendorsAttachmentResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\VendorsAttachmentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class VendorsAttachmentController extends Controller implements HasMiddleware
{
    private VendorsAttachmentRepositoryInterface $vendorsAttachmentRepository;

    public function __construct(VendorsAttachmentRepositoryInterface $vendorsAttachmentRepository)
    {
        $this->vendorsAttachmentRepository = $vendorsAttachmentRepository;
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
            $accounts = $this->vendorsAttachmentRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Attachment Retrieved Successfully', VendorsAttachmentResource::collection($accounts), 200);
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
            $vendors = $this->vendorsAttachmentRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Vendors Attachment Retrieved Successfully', PaginateResource::make($vendors, VendorsAttachmentResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VendorsAttachmentStoreRequest $request)
    {
        // Validasi request
        $validated = $request->validated();

        try {
            // Jika ada file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Simpan file di storage/app/public/vendors-attachments
                $path = $file->store('vendors-attachments', 'public');

                // Ambil extension dan ukuran file
                $extension = $file->getClientOriginalExtension();
                $size = $file->getSize(); // dalam bytes

                // Format ukuran file
                $sizeFormatted = $this->formatBytes($size);

                // Merge info file ke validated data
                $validated['document_path'] = $path;
                $validated['type_file'] = $extension;
                $validated['size_file'] = $sizeFormatted;
            }

            // Pastikan field wajib terisi
            $validated['vendor_id'] = $validated['vendor_id'] ?? null;

            // Buat record di DB
            $attachment = $this->vendorsAttachmentRepository->create($validated);

            return ResponseHelper::jsonResponse(
                true,
                'Vendors Attachment Created Successfully',
                new VendorsAttachmentResource($attachment),
                201
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


    /**
     * Helper untuk format ukuran file
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $vendors = $this->vendorsAttachmentRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Attachment Retrieved Successfully', new VendorsAttachmentResource($vendors), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Attachment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VendorsAttachmentUpdateRequest $request, string $id)
    {
        // Validasi request
        $validated = $request->validated();

        try {
            // Ambil record attachment dulu
            $attachment = $this->vendorsAttachmentRepository->getById($id);

            // Jika ada file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Simpan file baru ke storage/app/public/vendors-attachments
                $path = $file->store('vendors-attachments', 'public');

                // Update validated dengan info file baru
                $validated['document_path'] = $path;
                $validated['type_file'] = $file->getClientOriginalExtension();
                $validated['size_file'] = $this->formatBytes($file->getSize());
            }

            // Pastikan field wajib tetap ada
            $validated['vendor_id'] = $validated['vendor_id'] ?? $attachment->vendor_id;

            // Update record
            $updatedAttachment = $this->vendorsAttachmentRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(
                true,
                'Vendors Attachment Updated Successfully',
                new VendorsAttachmentResource($updatedAttachment),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Attachment Not Found', null, 404);
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
            $this->vendorsAttachmentRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Vendors Attachment Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Vendors Attachment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
