<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\FlowchartRequest;
use App\Http\Resources\FlowchartResource;
use App\Http\Resources\PaginateResource;
use App\Models\Flowchart;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class FlowchartController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['flowchart-menu']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['flowchart-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['flowchart-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['flowchart-delete']), only: ['destroy']),
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
