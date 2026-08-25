<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\GreetingResource;
use App\Http\Resources\PaginateResource;
use App\Models\Greeting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class GreetingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['greeting-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['greeting-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['greeting-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['greeting-delete']), only: ['destroy']),
            // `today` intentionally has no permission gate -- every logged-in
            // user should see today's greeting on their dashboard regardless
            // of whether they can manage the greeting calendar.
        ];
    }

    public function index(Request $request)
    {
        try {
            $greetings = Greeting::with('creator')
                ->when($request->search, fn ($q) => $q->where('title', 'like', '%'.$request->search.'%'))
                ->orderByDesc('greeting_date')
                ->paginate($request->row_per_page ?? 10, ['*'], 'page', $request->page ?? 1);

            return ResponseHelper::jsonResponse(true, 'Greetings Retrieved Successfully', PaginateResource::make($greetings, GreetingResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Active greetings that apply to today, for the authenticated user's
     * role -- consumed by DashboardWelcome, open to any logged-in user.
     */
    public function today(Request $request)
    {
        try {
            $userRole = $request->user()->roles->first()?->name;

            $greetings = Greeting::active()
                ->forAudience($userRole)
                ->forDate(now())
                ->orderBy('type')
                ->get();

            return ResponseHelper::jsonResponse(true, "Today's Greetings Retrieved Successfully", GreetingResource::collection($greetings), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:255',
            'greeting_date' => 'required|date',
            'is_recurring_yearly' => 'nullable|boolean',
            'type' => 'nullable|string|in:holiday,birthday,meeting,custom',
            'audience' => 'nullable|string|in:all,manager,operational_director,hr,finance,staff',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $greeting = Greeting::create([
                ...$validated,
                'is_recurring_yearly' => $validated['is_recurring_yearly'] ?? false,
                'type' => $validated['type'] ?? 'custom',
                'audience' => $validated['audience'] ?? 'all',
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Greeting Created Successfully', new GreetingResource($greeting), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string|max:255',
            'greeting_date' => 'sometimes|required|date',
            'is_recurring_yearly' => 'nullable|boolean',
            'type' => 'nullable|string|in:holiday,birthday,meeting,custom',
            'audience' => 'nullable|string|in:all,manager,operational_director,hr,finance,staff',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $greeting = Greeting::findOrFail($id);
            $greeting->update($validated);

            return ResponseHelper::jsonResponse(true, 'Greeting Updated Successfully', new GreetingResource($greeting), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Greeting Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $greeting = Greeting::findOrFail($id);
            $greeting->delete();

            return ResponseHelper::jsonResponse(true, 'Greeting Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Greeting Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
