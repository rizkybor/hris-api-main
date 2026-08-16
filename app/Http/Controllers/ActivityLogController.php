<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\PaginateResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ActivityLogController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['history-menu|history-view']), only: ['index', 'categories', 'statistics']),
        ];
    }

    /**
     * Paginated, filterable activity log listing.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'category' => 'nullable|string',
            'causer_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'row_per_page' => 'nullable|integer|min:5|max:100',
            'page' => 'nullable|integer',
        ]);

        try {
            $query = Activity::query()->with('causer')->latest('created_at');

            if (! empty($validated['category'])) {
                $query->where('log_name', $validated['category']);
            }

            if (! empty($validated['causer_id'])) {
                $query->where('causer_id', $validated['causer_id']);
            }

            if (! empty($validated['date_from'])) {
                $query->whereDate('created_at', '>=', $validated['date_from']);
            }

            if (! empty($validated['date_to'])) {
                $query->whereDate('created_at', '<=', $validated['date_to']);
            }

            if (! empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhereHas('causer', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $activities = $query->paginate($validated['row_per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

            return ResponseHelper::jsonResponse(true, 'Activity Log Retrieved Successfully', PaginateResource::make($activities, ActivityLogResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Distinct categories (log_name values) currently in use, for the filter dropdown.
     */
    public function categories()
    {
        try {
            $categories = Activity::query()
                ->select('log_name')
                ->distinct()
                ->orderBy('log_name')
                ->pluck('log_name');

            return ResponseHelper::jsonResponse(true, 'Categories Retrieved Successfully', $categories, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Simple summary counts for the History dashboard header.
     */
    public function statistics()
    {
        try {
            $total = Activity::count();
            $today = Activity::whereDate('created_at', now()->toDateString())->count();
            $thisWeek = Activity::where('created_at', '>=', now()->startOfWeek())->count();
            $byCategory = Activity::query()
                ->selectRaw('log_name, count(*) as total')
                ->groupBy('log_name')
                ->orderByDesc('total')
                ->get();

            return ResponseHelper::jsonResponse(true, 'Activity Statistics Retrieved Successfully', [
                'total' => $total,
                'today' => $today,
                'this_week' => $thisWeek,
                'by_category' => $byCategory,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
