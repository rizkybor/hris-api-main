<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectDocumentStoreRequest;
use App\Http\Requests\ProjectDocumentUpdateRequest;
use App\Http\Resources\ProjectDocumentResource;
use App\Interfaces\ProjectDocumentRepositoryInterface;
use App\Models\Project;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectDocumentController extends Controller implements HasMiddleware
{
    private ProjectDocumentRepositoryInterface $projectDocumentRepository;

    public function __construct(
        ProjectDocumentRepositoryInterface $projectDocumentRepository,
        private CloudinaryManager $cloudinary
    ) {
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
            $project = Project::findOrFail($validated['project_id']);

            $publicId = $this->cloudinary->uploadRaw(
                $file,
                CloudinaryFolders::projectFiles(),
                CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-doc')
            );

            $validated['document_path'] = $publicId;
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
                $project = $document->project;

                $publicId = $this->cloudinary->uploadRaw(
                    $file,
                    CloudinaryFolders::projectFiles(),
                    CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-doc')
                );

                $this->cloudinary->delete($document->document_path, 'raw');

                $validated['document_path'] = $publicId;
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

            $this->cloudinary->delete($document->document_path, 'raw');

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
