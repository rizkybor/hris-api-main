<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ClientAttachmentStoreRequest;
use App\Http\Requests\ClientAttachmentUpdateRequest;
use App\Http\Resources\ClientAttachmentResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\ClientAttachmentRepositoryInterface;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryResourceType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ClientAttachmentController extends Controller implements HasMiddleware
{
    private ClientAttachmentRepositoryInterface $clientsAttachmentRepository;

    public function __construct(
        ClientAttachmentRepositoryInterface $clientsAttachmentRepository,
        private CloudinaryManager $cloudinary
    ) {
        $this->clientsAttachmentRepository = $clientsAttachmentRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['clients-attachment-list|clients-attachment-create|clients-attachment-edit|clients-attachment-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['clients-attachment-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['clients-attachment-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['clients-attachment-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->clientsAttachmentRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Client Attachment Retrieved Successfully', ClientAttachmentResource::collection($accounts), 200);
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
            $clients = $this->clientsAttachmentRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Client Attachment Retrieved Successfully', PaginateResource::make($clients, ClientAttachmentResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientAttachmentStoreRequest $request)
    {
        // Validasi request
        $validated = $request->validated();

        try {
            // Jika ada file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                $publicId = $this->cloudinary->uploadAuto(
                    $file,
                    CloudinaryFolders::companyFiles('clients'),
                    CloudinaryFolders::filename('client-'.($validated['client_id'] ?? 'unassigned').'-attachment')
                );

                $extension = $file->getClientOriginalExtension();
                $size = $file->getSize(); // dalam bytes

                // Format ukuran file
                $sizeFormatted = $this->formatBytes($size);

                // Merge info file ke validated data
                $validated['document_path'] = $publicId;
                $validated['type_file'] = $extension;
                $validated['size_file'] = $sizeFormatted;
            }

            // Pastikan field wajib terisi
            $validated['client_id'] = $validated['client_id'] ?? null;

            // Buat record di DB
            $attachment = $this->clientsAttachmentRepository->create($validated);

            return ResponseHelper::jsonResponse(
                true,
                'Client Attachment Created Successfully',
                new ClientAttachmentResource($attachment),
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
            $clients = $this->clientsAttachmentRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Client Attachment Retrieved Successfully', new ClientAttachmentResource($clients), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Attachment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClientAttachmentUpdateRequest $request, string $id)
    {
        // Validasi request
        $validated = $request->validated();

        try {
            // Ambil record attachment dulu
            $attachment = $this->clientsAttachmentRepository->getById($id);

            // Jika ada file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                $this->cloudinary->delete($attachment->document_path, CloudinaryResourceType::fromExtension($attachment->type_file));

                $publicId = $this->cloudinary->uploadAuto(
                    $file,
                    CloudinaryFolders::companyFiles('clients'),
                    CloudinaryFolders::filename('client-'.($validated['client_id'] ?? $attachment->client_id).'-attachment')
                );

                // Update validated dengan info file baru
                $validated['document_path'] = $publicId;
                $validated['type_file'] = $file->getClientOriginalExtension();
                $validated['size_file'] = $this->formatBytes($file->getSize());
            }

            // Pastikan field wajib tetap ada
            $validated['client_id'] = $validated['client_id'] ?? $attachment->client_id;

            // Update record
            $updatedAttachment = $this->clientsAttachmentRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(
                true,
                'Client Attachment Updated Successfully',
                new ClientAttachmentResource($updatedAttachment),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Attachment Not Found', null, 404);
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
            $attachment = $this->clientsAttachmentRepository->getById($id);
            $this->cloudinary->delete($attachment->document_path, CloudinaryResourceType::fromExtension($attachment->type_file));

            $this->clientsAttachmentRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Client Attachment Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Attachment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
