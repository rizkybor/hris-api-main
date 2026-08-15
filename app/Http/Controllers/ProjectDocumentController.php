<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectDocumentStoreRequest;
use App\Http\Requests\ProjectDocumentUpdateRequest;
use App\Http\Resources\ProjectDocumentResource;
use App\Interfaces\ProjectDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectDocumentController extends Controller implements HasMiddleware
{
    private ProjectDocumentRepositoryInterface $projectDocumentRepository;

    public function __construct(ProjectDocumentRepositoryInterface $projectDocumentRepository)
    {
        $this->projectDocumentRepository = $projectDocumentRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['store', 'update']),
            new Middleware(PermissionMiddleware::using(['project-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        try {
            $documents = $this->projectDocumentRepository->getByProjectId(
                (int) $request->project_id,
                $request->search
            );

            return ResponseHelper::jsonResponse(true, 'Project Documents Retrieved Successfully', ProjectDocumentResource::collection($documents), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(ProjectDocumentStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $file = $request->file('document_path');
            $storedPath = $file->store('project-documents', 'public');

            $validated['document_path'] = $storedPath;
            $validated['type_file'] = $file->getClientOriginalExtension();
            $validated['size_file'] = $this->formatBytes($file->getSize());

            $document = $this->projectDocumentRepository->create($validated);

            return ResponseHelper::jsonResponse(true, 'Project Document Uploaded Successfully', new ProjectDocumentResource($document), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $document = $this->projectDocumentRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Project Document Retrieved Successfully', new ProjectDocumentResource($document), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Document Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(ProjectDocumentUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $document = $this->projectDocumentRepository->getById($id);

            if ($request->hasFile('document_path')) {
                $file = $request->file('document_path');
                $storedPath = $file->store('project-documents', 'public');

                if ($document->document_path) {
                    Storage::disk('public')->delete($document->document_path);
                }

                $validated['document_path'] = $storedPath;
                $validated['type_file'] = $file->getClientOriginalExtension();
                $validated['size_file'] = $this->formatBytes($file->getSize());
            }

            $updated = $this->projectDocumentRepository->update($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Project Document Updated Successfully', new ProjectDocumentResource($updated), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Document Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $document = $this->projectDocumentRepository->getById($id);

            if ($document->document_path) {
                Storage::disk('public')->delete($document->document_path);
            }

            $this->projectDocumentRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Project Document Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Project Document Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
