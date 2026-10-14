<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\FlowchartRequest;
use App\Http\Resources\FlowchartResource;
use App\Http\Resources\PaginateResource;
use App\Models\Flowchart;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class FlowchartController extends Controller implements HasMiddleware
{
    public function __construct(protected CloudinaryManager $cloudinary) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['flowchart-menu']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['flowchart-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['flowchart-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['flowchart-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['flowchart-create|flowchart-edit']), only: ['uploadPreview']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $flowcharts = Flowchart::with(['creator', 'updater'])
                ->when($request->search, fn ($q) => $q->search($request->search))
                ->latest('updated_at')
                ->paginate($request->row_per_page ?? 10);

            return ResponseHelper::jsonResponse(true, 'Flowcharts Retrieved Successfully', PaginateResource::make($flowcharts, FlowchartResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(FlowchartRequest $request)
    {
        try {
            $flowchart = Flowchart::create([
                ...$request->validated(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Flowchart Created Successfully', (new FlowchartResource($flowchart->load(['creator', 'updater'])))->withData(), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $flowchart = Flowchart::with(['creator', 'updater'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Flowchart Retrieved Successfully', (new FlowchartResource($flowchart))->withData(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Flowchart Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(FlowchartRequest $request, int $id)
    {
        try {
            $flowchart = Flowchart::findOrFail($id);
            $flowchart->update([
                ...$request->validated(),
                'updated_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Flowchart Updated Successfully', (new FlowchartResource($flowchart->load(['creator', 'updater'])))->withData(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Flowchart Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Stores the editor's PNG snapshot of the diagram (sent right after each
     * save), replacing the previous one.
     */
    public function uploadPreview(Request $request, int $id)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
        ]);

        try {
            $flowchart = Flowchart::findOrFail($id);
            $previous = $flowchart->preview_image;

            $publicId = $this->cloudinary->uploadImage(
                $request->file('image'),
                CloudinaryFolders::companyFiles('flowcharts'),
                CloudinaryFolders::filename('flowchart-'.$flowchart->id)
            );
            // Not touching updated_at/updated_by: a preview refresh isn't an edit.
            $flowchart->forceFill(['preview_image' => $publicId])->saveQuietly();

            if ($previous && $previous !== $publicId) {
                $this->cloudinary->delete($previous);
            }

            return ResponseHelper::jsonResponse(true, 'Flowchart Preview Saved', ['preview_url' => CloudinaryUrl::image($publicId)], 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Flowchart Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Preview upload failed: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            Flowchart::findOrFail($id)->delete();

            return ResponseHelper::jsonResponse(true, 'Flowchart Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Flowchart Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
