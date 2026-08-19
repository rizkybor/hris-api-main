<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProjectTaskCommentResource;
use App\Models\EmployeeProfile;
use App\Models\ProjectTask;
use App\Models\ProjectTaskComment;
use App\Models\User;
use App\Notifications\ProjectTaskCommentMention;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectTaskCommentController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['task-list']), only: ['index', 'store']),
        ];
    }

    public function index(string $taskId)
    {
        try {
            $task = ProjectTask::findOrFail($taskId);

            $comments = $task->comments()->with(['user', 'parent.user', 'task.project'])->get();

            return ResponseHelper::jsonResponse(true, 'Comments Retrieved Successfully', ProjectTaskCommentResource::collection($comments), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request, string $taskId)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:project_task_comments,id',
            'mentioned_employee_ids' => 'nullable|array',
            'mentioned_employee_ids.*' => 'integer|exists:employee_profiles,id',
        ]);

        try {
            $task = ProjectTask::with('project')->findOrFail($taskId);
            /** @var User $user */
            $user = $request->user();
            $employeeId = $user->employeeProfile?->id;

            $isManager = $user->hasRole('manager');
            $isParticipant = $employeeId && $task->project && $task->project->isEmployeeProjectParticipant($employeeId);
            // A task's assignee isn't required to be on the project's team
            // roster (assignee_id is only validated against employee_profiles,
            // not project participation), so without this check someone
            // assigned to a task outside their own team couldn't comment on
            // the very task they're doing.
            $isAssignee = $employeeId && $task->assignee_id === $employeeId;

            if (! $isManager && ! $isParticipant && ! $isAssignee) {
                return ResponseHelper::jsonResponse(false, 'Only the project leader, managers, the task\'s assignee, or employees assigned to this project\'s teams can comment on its tasks', null, 403);
            }

            if (! empty($validated['parent_id'])) {
                $parentBelongsToTask = $task->comments()->where('id', $validated['parent_id'])->exists();
                if (! $parentBelongsToTask) {
                    return ResponseHelper::jsonResponse(false, 'Parent comment does not belong to this task', null, 422);
                }
            }

            $mentionedIds = $validated['mentioned_employee_ids'] ?? [];
            $participantIds = $task->project->getParticipantEmployeeIds();
            $invalidMentions = collect($mentionedIds)->reject(
                fn ($id) => $task->assignee_id === $id || $participantIds->contains($id)
            );
            if ($invalidMentions->isNotEmpty()) {
                return ResponseHelper::jsonResponse(false, 'You can only mention the project leader, the task\'s assignee, or employees assigned to this project\'s teams', null, 422);
            }

            if ($employeeId && in_array($employeeId, $mentionedIds, true)) {
                return ResponseHelper::jsonResponse(false, 'You can\'t mention yourself', null, 422);
            }

            $comment = $task->comments()->create([
                'parent_id' => $validated['parent_id'] ?? null,
                'user_id' => Auth::id(),
                'body' => $validated['body'],
                'mentioned_employee_ids' => $mentionedIds,
            ]);

            $comment->load(['user', 'parent.user', 'task.project']);

            $mentionedEmployees = empty($mentionedIds)
                ? collect()
                : EmployeeProfile::with('user')->whereIn('id', $mentionedIds)->get();

            $this->notifyMentionedEmployees($comment, $mentionedEmployees);
            $this->logCommentActivity($task, $comment, $user, $mentionedIds, $mentionedEmployees);

            return ResponseHelper::jsonResponse(true, 'Comment Added Successfully', new ProjectTaskCommentResource($comment), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $comment = ProjectTaskComment::with('task.project')->findOrFail($id);
            /** @var User $user */
            $user = $request->user();

            $isManager = $user->hasRole('manager');
            $employeeId = $user->employeeProfile?->id;
            $isProjectLeader = $employeeId && $comment->task?->project && $comment->task->project->isProjectLeader($employeeId);

            if (! $isManager && ! $isProjectLeader) {
                return ResponseHelper::jsonResponse(false, 'Only the project leader or a manager can delete comments', null, 403);
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
     * @param  \Illuminate\Support\Collection<int, EmployeeProfile>  $mentionedEmployees
     */
    private function notifyMentionedEmployees(ProjectTaskComment $comment, $mentionedEmployees): void
    {
        if ($mentionedEmployees->isEmpty()) {
            return;
        }

        $mentionedUsers = $mentionedEmployees->pluck('user')->filter();

        Notification::send($mentionedUsers, new ProjectTaskCommentMention($comment));
    }

    /**
     * @param  array<int>  $mentionedIds
     * @param  \Illuminate\Support\Collection<int, EmployeeProfile>  $mentionedEmployees
     */
    private function logCommentActivity(ProjectTask $task, ProjectTaskComment $comment, User $user, array $mentionedIds, $mentionedEmployees): void
    {
        $description = $comment->parent_id ? 'replied to a comment' : 'commented';

        if (! empty($mentionedIds)) {
            $mentionedNames = $mentionedEmployees->pluck('user.name')->filter()->implode(', ');

            if ($mentionedNames) {
                $description .= " and mentioned {$mentionedNames}";
            }
        }

        activity('Project')
            ->causedBy($user)
            ->performedOn($task)
            ->event('commented')
            ->withProperties([
                'comment_id' => $comment->id,
                'mentioned_employee_ids' => $mentionedIds,
            ])
            ->log("{$description} on \"{$task->name}\"");
    }
}
