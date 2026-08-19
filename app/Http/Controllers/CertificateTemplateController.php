<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\CertificateTemplateResource;
use App\Models\CertificateTemplate;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CertificateTemplateController extends Controller implements HasMiddleware
{
    public function __construct(private CloudinaryManager $cloudinary) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['certificate-menu']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['certificate-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['certificate-delete']), only: ['destroy']),
        ];
    }

    public function index()
    {
        $templates = CertificateTemplate::with('creator')->latest()->get();

        return ResponseHelper::jsonResponse(true, 'Certificate Templates Retrieved Successfully', CertificateTemplateResource::collection($templates), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'background' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        try {
            $publicId = $this->cloudinary->uploadImage(
                $request->file('background'),
                CloudinaryFolders::companyFiles('certificate-templates'),
                CloudinaryFolders::filename('certificate-template')
            );

            $template = CertificateTemplate::create([
                'name' => $validated['name'],
                'background_path' => $publicId,
                'created_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Certificate Template Uploaded Successfully', new CertificateTemplateResource($template), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $template = CertificateTemplate::findOrFail($id);

            $this->cloudinary->delete($template->background_path);

            $template->delete();

            return ResponseHelper::jsonResponse(true, 'Certificate Template Deleted Successfully', null, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
