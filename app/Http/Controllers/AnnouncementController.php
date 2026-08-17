<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\PaginateResource;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AnnouncementController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['announcement-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['announcement-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['announcement-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['announcement-delete']), only: ['destroy']),
        ];
    }

    /**
     * Active announcements visible to the authenticated user's role, newest/pinned first.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'row_per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        try {
            $userRole = $request->user()->roles->first()?->name;

            $announcements = Announcement::with('creator')
                ->active()
                ->forAudience($userRole)
                ->orderByDesc('is_pinned')
                ->latest('created_at')
                ->paginate($validated['row_per_page'] ?? 10, ['*'], 'page', $validated['page'] ?? 1);

            return ResponseHelper::jsonResponse(true, 'Announcements Retrieved Successfully', PaginateResource::make($announcements, AnnouncementResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $announcement = Announcement::with('creator')->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Announcement Retrieved Successfully', new AnnouncementResource($announcement), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Announcement Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => 'nullable|string|in:all,manager,operational_director,hr,finance,staff',
            'is_pinned' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        try {
            $announcement = Announcement::create([
                ...$validated,
                'audience' => $validated['audience'] ?? 'all',
                'created_by' => $request->user()->id,
            ]);

            $recipients = ($validated['audience'] ?? 'all') === 'all'
                ? User::all()
                : User::role($validated['audience'])->get();

            Notification::send($recipients, new AnnouncementPublished($announcement));

            return ResponseHelper::jsonResponse(true, 'Announcement Published Successfully', new AnnouncementResource($announcement), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'audience' => 'nullable|string|in:all,manager,operational_director,hr,finance,staff',
            'is_pinned' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->update($validated);

            return ResponseHelper::jsonResponse(true, 'Announcement Updated Successfully', new AnnouncementResource($announcement), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Announcement Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();

            return ResponseHelper::jsonResponse(true, 'Announcement Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Announcement Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
