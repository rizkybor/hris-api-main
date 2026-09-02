<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AnalyticsSourceStoreRequest;
use App\Http\Requests\AnalyticsSourceUpdateRequest;
use App\Http\Resources\AnalyticsSourceResource;
use App\Models\AnalyticsSource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AnalyticsSourceController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['analytics-menu|analytics-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['analytics-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['analytics-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['analytics-delete']), only: ['destroy']),
        ];
    }

    /**
     * Every source, grouped by category server-side so the frontend just
     * renders what it's given rather than re-deriving the grouping itself.
     */
    public function index(Request $request)
    {
        try {
            $query = AnalyticsSource::with('creator:id,name')->orderBy('category')->orderBy('name');

            if ($request->search) {
                $query->search($request->search);
            }

            $sources = $query->get();

            $grouped = $sources
                ->groupBy('category')
                ->map(fn ($items, $category) => [
                    'category' => $category,
                    'sources' => AnalyticsSourceResource::collection($items)->resolve(),
                ])
                ->values();

            return ResponseHelper::jsonResponse(true, 'Analytics Sources Retrieved Successfully', $grouped, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(AnalyticsSourceStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $validated['created_by'] = Auth::id();
            $source = AnalyticsSource::create($validated);

            return ResponseHelper::jsonResponse(true, 'Analytics Source Created Successfully', new AnalyticsSourceResource($source), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function update(AnalyticsSourceUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $source = AnalyticsSource::findOrFail($id);
            $source->update($validated);

            return ResponseHelper::jsonResponse(true, 'Analytics Source Updated Successfully', new AnalyticsSourceResource($source), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Analytics Source Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $source = AnalyticsSource::findOrFail($id);
            $source->delete();

            return ResponseHelper::jsonResponse(true, 'Analytics Source Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Analytics Source Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
