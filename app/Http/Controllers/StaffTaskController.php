<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\StaffTaskResource;
use App\Models\EmployeeProfile;
use App\Models\StaffTask;
use App\Models\StaffTaskAssignee;
use App\Models\User;
use App\Notifications\StaffTaskAssigned;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Middleware\PermissionMiddleware;

class StaffTaskController extends Controller implements HasMiddleware
{
    /**
     * Who may hand out staff tasks -- a fixed allow-list re-checked in the
     * controller as the source of truth (same "belt and suspenders" pattern
     * as MeetingNoteController), so it holds even if permissions drift.
     * Unlike Meeting Note, this list DOES include superadmin and does NOT
     * include hr, per spec.
     */
    private const ALLOWED_ROLES = ['superadmin', 'manager', 'finance', 'operational_director'];

    private const RELATIONS = ['creator', 'assignees.employee.user'];

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['staff-task-menu|staff-task-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['staff-task-create', 'staff-task-edit']), only: ['staffOptions']),
            new Middleware(PermissionMiddleware::using(['staff-task-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['staff-task-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['staff-task-delete']), only: ['destroy']),
        ];
    }

    private function assertAllowedRole()
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to Staff Tasks.', null, 403);
        }

        return null;
    }

    /**
     * Shared repository: any of the allowed roles sees every task, not
     * just the ones they personally created.
     */
    public function index(Request $request)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            $query = StaffTask::query()
                ->with(self::RELATIONS)
                ->orderByDesc('created_at');

            if ($request->search) {
                $query->where('title', 'like', '%'.$request->search.'%');
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $tasks = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Staff Tasks Retrieved Successfully', PaginateResource::make($tasks, StaffTaskResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'assignment_mode' => ['required', 'string', 'in:all_staff,selected'],
            'assignee_employee_ids' => ['required_if:assignment_mode,selected', 'array'],
            'assignee_employee_ids.*' => ['integer', 'exists:employee_profiles,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $task = DB::transaction(function () use ($validated, $user) {
                $task = StaffTask::create([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'due_date' => $validated['due_date'],
                    'assignment_mode' => $validated['assignment_mode'],
                    'created_by' => $user->id,
                ]);

                $employeeIds = $this->resolveAssigneeEmployeeIds($validated);

                foreach ($employeeIds as $employeeId) {
                    StaffTaskAssignee::create([
                        'staff_task_id' => $task->id,
                        'employee_id' => $employeeId,
                        'status' => 'todo',
                    ]);
                }

                return $task;
            });

            $task->load(self::RELATIONS);

            $this->notifyAssignees($task);

            return ResponseHelper::jsonResponse(true, 'Staff Task Created Successfully', new StaffTaskResource($task), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            $task = StaffTask::with(self::RELATIONS)->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Staff Task Retrieved Successfully', new StaffTaskResource($task), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Staff Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Any of the allowed roles can edit any task (shared ownership), same
     * as Meeting Note. Re-syncing assignees preserves each kept assignee's
     * existing status/progress -- only newly added people start at "todo",
     * and only they get a fresh assignment notification.
     */
    public function update(Request $request, string $id)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['sometimes', 'required', 'date'],
            'assignment_mode' => ['sometimes', 'required', 'string', 'in:all_staff,selected'],
            'assignee_employee_ids' => ['required_if:assignment_mode,selected', 'array'],
            'assignee_employee_ids.*' => ['integer', 'exists:employee_profiles,id'],
        ]);

        try {
            $task = StaffTask::with('assignees')->findOrFail($id);

            $newlyAddedIds = DB::transaction(function () use ($task, $validated) {
                $task->update(collect($validated)->only(['title', 'description', 'due_date', 'assignment_mode'])->toArray());

                $newlyAddedIds = [];

                if (isset($validated['assignment_mode'])) {
                    $employeeIds = collect($this->resolveAssigneeEmployeeIds($validated));
                    $existingIds = $task->assignees->pluck('employee_id');

                    $toAdd = $employeeIds->diff($existingIds);
                    $toRemove = $existingIds->diff($employeeIds);

                    foreach ($toAdd as $employeeId) {
                        StaffTaskAssignee::create([
                            'staff_task_id' => $task->id,
                            'employee_id' => $employeeId,
                            'status' => 'todo',
                        ]);
                    }

                    if ($toRemove->isNotEmpty()) {
                        StaffTaskAssignee::where('staff_task_id', $task->id)
                            ->whereIn('employee_id', $toRemove)
                            ->delete();
                    }

                    $newlyAddedIds = $toAdd->values()->all();
                }

                return $newlyAddedIds;
            });

            $task->load(self::RELATIONS);

            if (! empty($newlyAddedIds)) {
                $this->notifyAssignees($task, $newlyAddedIds);
            }

            return ResponseHelper::jsonResponse(true, 'Staff Task Updated Successfully', new StaffTaskResource($task), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Staff Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            $task = StaffTask::findOrFail($id);
            $task->delete();

            return ResponseHelper::jsonResponse(true, 'Staff Task Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Staff Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Lean {id, name} list of every employee with the "staff" role, for the
     * assignee picker on the create/edit form -- deliberately not routed
     * through EmployeeProfileController::index() since that returns the
     * full profile shape and has no role filter.
     */
    public function staffOptions()
    {
        try {
            $employees = EmployeeProfile::whereHas('user', fn ($q) => $q->role('staff'))
                ->with('user:id,name')
                ->get(['id', 'user_id'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user?->name]);

            return ResponseHelper::jsonResponse(true, 'Staff Options Retrieved Successfully', $employees, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Tasks assigned to the logged-in employee, for the "My Tasks" page.
     * Open to any authenticated employee -- no staff-task-* permission
     * needed, mirroring ProjectTaskController::getMyTasks().
     */
    public function myTasks(Request $request)
    {
        try {
            $employeeId = $request->user()->employeeProfile?->id;

            if (! $employeeId) {
                return ResponseHelper::jsonResponse(true, 'My Staff Tasks Retrieved Successfully', [], 200);
            }

            $request->attributes->set('viewer_employee_id', $employeeId);

            $tasks = StaffTask::with(self::RELATIONS)
                ->whereHas('assignees', fn ($q) => $q->where('employee_id', $employeeId))
                ->orderBy('due_date')
                ->get();

            return ResponseHelper::jsonResponse(true, 'My Staff Tasks Retrieved Successfully', StaffTaskResource::collection($tasks), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Self-service status update -- an assignee marking their own daily
     * progress. Deliberately identity-gated (must be your own assignment
     * row) rather than permission-gated, same pattern as the Project Cash
     * Ledger's canManage() check.
     */
    public function updateMyStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:todo,in_progress,done'],
        ]);

        try {
            $employeeId = $request->user()->employeeProfile?->id;

            $assignee = StaffTaskAssignee::where('staff_task_id', $id)
                ->where('employee_id', $employeeId)
                ->first();

            if (! $assignee) {
                return ResponseHelper::jsonResponse(false, 'You are not assigned to this task', null, 403);
            }

            $assignee->update([
                'status' => $validated['status'],
                'status_updated_at' => now(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Status Updated Successfully', [
                'id' => $assignee->id,
                'status' => $assignee->status,
                'status_updated_at' => $assignee->status_updated_at,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * @param  array{assignment_mode: string, assignee_employee_ids?: array<int>}  $validated
     * @return array<int>
     */
    private function resolveAssigneeEmployeeIds(array $validated): array
    {
        if ($validated['assignment_mode'] === 'all_staff') {
            return EmployeeProfile::whereHas('user', fn ($q) => $q->role('staff'))->pluck('id')->all();
        }

        return $validated['assignee_employee_ids'] ?? [];
    }

    /**
     * @param  array<int>|null  $onlyEmployeeIds  Restrict to these employee IDs (used when re-syncing on update); null notifies every current assignee (used on create).
     */
    private function notifyAssignees(StaffTask $task, ?array $onlyEmployeeIds = null): void
    {
        $assignees = $onlyEmployeeIds === null
            ? $task->assignees
            : $task->assignees->whereIn('employee_id', $onlyEmployeeIds);

        $recipients = $assignees->pluck('employee.user')->filter();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new StaffTaskAssigned($task));
    }
}
