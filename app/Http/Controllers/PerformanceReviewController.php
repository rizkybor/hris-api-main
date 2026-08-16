<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PerformanceReviewResource;
use App\Models\PerformanceReview;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceReviewController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['performance-review-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['performance-review-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['performance-review-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['performance-review-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['performance-review-my-reviews']), only: ['myReviews']),
            new Middleware(PermissionMiddleware::using(['performance-review-acknowledge']), only: ['acknowledge']),
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'row_per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        try {
            $reviews = PerformanceReview::with(['employee.user', 'employee.jobInformation', 'reviewer'])
                ->when($validated['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
                ->latest('period_start')
                ->paginate($validated['row_per_page'] ?? 10, ['*'], 'page', $validated['page'] ?? 1);

            return ResponseHelper::jsonResponse(true, 'Performance Reviews Retrieved Successfully', PaginateResource::make($reviews, PerformanceReviewResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function myReviews(Request $request)
    {
        try {
            $employeeId = $request->user()->employeeProfile?->id;

            $reviews = PerformanceReview::with(['reviewer'])
                ->where('employee_id', $employeeId)
                ->latest('period_start')
                ->get();

            return ResponseHelper::jsonResponse(true, 'My Performance Reviews Retrieved Successfully', PerformanceReviewResource::collection($reviews), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $review = PerformanceReview::with(['employee.user', 'employee.jobInformation', 'reviewer'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Performance Review Retrieved Successfully', new PerformanceReviewResource($review), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Performance Review Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employee_profiles,id',
            'period' => 'required|string|max:50',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'overall_rating' => 'required|numeric|min:1|max:5',
            'category_scores' => 'nullable|array',
            'category_scores.*' => 'numeric|min:1|max:5',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'goals_next_period' => 'nullable|string',
        ]);

        try {
            $review = PerformanceReview::create([
                ...$validated,
                'reviewer_id' => $request->user()->id,
                'status' => 'submitted',
            ]);

            return ResponseHelper::jsonResponse(true, 'Performance Review Submitted Successfully', new PerformanceReviewResource($review->load(['employee.user', 'reviewer'])), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'period' => 'sometimes|required|string|max:50',
            'period_start' => 'sometimes|required|date',
            'period_end' => 'sometimes|required|date|after_or_equal:period_start',
            'overall_rating' => 'sometimes|required|numeric|min:1|max:5',
            'category_scores' => 'nullable|array',
            'category_scores.*' => 'numeric|min:1|max:5',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'goals_next_period' => 'nullable|string',
        ]);

        try {
            $review = PerformanceReview::findOrFail($id);
            $review->update($validated);

            return ResponseHelper::jsonResponse(true, 'Performance Review Updated Successfully', new PerformanceReviewResource($review), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Performance Review Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $review = PerformanceReview::findOrFail($id);
            $review->delete();

            return ResponseHelper::jsonResponse(true, 'Performance Review Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Performance Review Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Employee acknowledges they've read their own review.
     */
    public function acknowledge(Request $request, string $id)
    {
        try {
            $employeeId = $request->user()->employeeProfile?->id;

            $review = PerformanceReview::where('employee_id', $employeeId)->findOrFail($id);
            $review->update([
                'status' => 'acknowledged',
                'employee_acknowledged_at' => now(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Performance Review Acknowledged Successfully', new PerformanceReviewResource($review), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Performance Review Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
