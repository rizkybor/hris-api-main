<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\StaffTaskCommentResource;
use App\Models\EmployeeProfile;
use App\Models\StaffTask;
use App\Models\StaffTaskComment;
use App\Models\User;
use App\Notifications\StaffTaskCommentMention;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class StaffTaskCommentController extends Controller
{
    private const ALLOWED_ROLES = ['superadmin', 'manager', 'finance', 'operational_director'];

    public function index(Request $request, string $staffTaskId)
    {
        try {
            $task = StaffTask::findOrFail($staffTaskId);

            if ($guardError = $this->assertCanView($task, $request)) {
                return $guardError;
            }

            $comments = $task->comments()->with(['user', 'parent.user'])->get();

            return ResponseHelper::jsonResponse(true, 'Comments Retrieved Successfully', StaffTaskCommentResource::collection($comments), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Staff Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request, string $staffTaskId)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:staff_task_comments,id',
            'mentioned_employee_ids' => 'nullable|array',
            'mentioned_employee_ids.*' => 'integer|exists:employee_profiles,id',
        ]);

        try {
            $task = StaffTask::with('assignees')->findOrFail($staffTaskId);
            /** @var User $user */
            $user = $request->user();

            if ($guardError = $this->assertCanView($task, $request)) {
                return $guardError;
            }

            $employeeId = $user->employeeProfile?->id;

            if (! empty($validated['parent_id'])) {
                $parentBelongsToTask = $task->comments()->where('id', $validated['parent_id'])->exists();
                if (! $parentBelongsToTask) {
                    return ResponseHelper::jsonResponse(false, 'Parent comment does not belong to this task', null, 422);
                }
            }

            $mentionedIds = $validated['mentioned_employee_ids'] ?? [];
            if (! empty($mentionedIds)) {
                $allowedMentionIds = $task->assignees->pluck('employee_id');
                $creatorEmployeeId = $task->creator?->employeeProfile?->id;
                if ($creatorEmployeeId) {
                    $allowedMentionIds->push($creatorEmployeeId);
                }

                $invalidMentions = collect($mentionedIds)->diff($allowedMentionIds);
                if ($invalidMentions->isNotEmpty()) {
                    return ResponseHelper::jsonResponse(false, 'You can only mention this task\'s assignees or its creator', null, 422);
                }

                if ($employeeId && in_array($employeeId, $mentionedIds, true)) {
                    return ResponseHelper::jsonResponse(false, 'You can\'t mention yourself', null, 422);
                }
            }

            $comment = $task->comments()->create([
                'parent_id' => $validated['parent_id'] ?? null,
                'user_id' => Auth::id(),
                'body' => $validated['body'],
                'mentioned_employee_ids' => $mentionedIds,
            ]);

            $comment->load(['user', 'parent.user']);

            $mentionedEmployees = empty($mentionedIds)
                ? collect()
                : EmployeeProfile::with('user')->whereIn('id', $mentionedIds)->get();

            $this->notifyMentionedEmployees($comment, $mentionedEmployees);
            $this->logCommentActivity($task, $comment, $user, $mentionedIds, $mentionedEmployees);

            return ResponseHelper::jsonResponse(true, 'Comment Added Successfully', new StaffTaskCommentResource($comment), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Staff Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $comment = StaffTaskComment::findOrFail($id);
            /** @var User $user */
            $user = $request->user();

            if (! $user->hasRole('manager') && $comment->user_id !== $user->id) {
                return ResponseHelper::jsonResponse(false, 'Only the comment author or a manager can delete comments', null, 403);
            }

            $comment->delete();

            return ResponseHelper::jsonResponse(true, 'Comment Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Comment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Commenting is open to the task's assignees, its creator, or any of
     * the roles allowed to manage Staff Tasks -- broader than Meeting
     * Note's "attendees only" rule, since a task's audience can be as wide
     * as "all staff" and everyone assigned should be able to discuss it.
     */
    private function assertCanView(StaffTask $task, Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $employeeId = $user->employeeProfile?->id;

        $isAssignee = $employeeId && $task->isEmployeeAssignee($employeeId);
        $isCreator = $task->created_by === $user->id;
        $isAllowedRole = $user->hasAnyRole(self::ALLOWED_ROLES);

        if (! $isAssignee && ! $isCreator && ! $isAllowedRole) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to this task', null, 403);
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EmployeeProfile>  $mentionedEmployees
     */
    private function notifyMentionedEmployees(StaffTaskComment $comment, $mentionedEmployees): void
    {
        if ($mentionedEmployees->isEmpty()) {
            return;
        }

        $mentionedUsers = $mentionedEmployees->pluck('user')->filter();

        Notification::send($mentionedUsers, new StaffTaskCommentMention($comment));
    }

    /**
     * @param  array<int>  $mentionedIds
     * @param  \Illuminate\Support\Collection<int, EmployeeProfile>  $mentionedEmployees
     */
    private function logCommentActivity(StaffTask $task, StaffTaskComment $comment, User $user, array $mentionedIds, $mentionedEmployees): void
    {
        $description = $comment->parent_id ? 'replied to a comment' : 'commented';

        if (! empty($mentionedIds)) {
            $mentionedNames = $mentionedEmployees->pluck('user.name')->filter()->implode(', ');

            if ($mentionedNames) {
                $description .= " and mentioned {$mentionedNames}";
            }
        }

        activity('Staff Task')
            ->causedBy($user)
            ->performedOn($task)
            ->event('commented')
            ->withProperties([
                'comment_id' => $comment->id,
                'mentioned_employee_ids' => $mentionedIds,
            ])
            ->log("{$description} on \"{$task->title}\"");
    }
}
