<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MeetingNoteCommentResource;
use App\Models\EmployeeProfile;
use App\Models\MeetingNote;
use App\Models\MeetingNoteComment;
use App\Models\User;
use App\Notifications\MeetingNoteCommentMention;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Middleware\PermissionMiddleware;

class MeetingNoteCommentController extends Controller implements HasMiddleware
{
    private const ALLOWED_ROLES = ['manager', 'operational_director', 'hr', 'finance'];

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['meeting-note-list']), only: ['index', 'store']),
        ];
    }

    public function index(Request $request, string $meetingNoteId)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to Meeting Note.', null, 403);
        }

        try {
            $note = MeetingNote::findOrFail($meetingNoteId);

            $comments = $note->comments()->with(['user', 'parent.user'])->get();

            return ResponseHelper::jsonResponse(true, 'Comments Retrieved Successfully', MeetingNoteCommentResource::collection($comments), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request, string $meetingNoteId)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:meeting_note_comments,id',
            'mentioned_employee_ids' => 'nullable|array',
            'mentioned_employee_ids.*' => 'integer|exists:employee_profiles,id',
        ]);

        try {
            $note = MeetingNote::findOrFail($meetingNoteId);
            /** @var User $user */
            $user = $request->user();

            if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
                return ResponseHelper::jsonResponse(false, 'You do not have access to Meeting Note.', null, 403);
            }

            $employeeId = $user->employeeProfile?->id;
            $isAttendee = $employeeId && $note->isEmployeeAttendee($employeeId);
            $isCreator = $note->created_by === $user->id;
            if (! $isAttendee && ! $isCreator) {
                return ResponseHelper::jsonResponse(false, 'Only employees checked in as Internal Attendees can comment on this note', null, 403);
            }

            if (! empty($validated['parent_id'])) {
                $parentBelongsToNote = $note->comments()->where('id', $validated['parent_id'])->exists();
                if (! $parentBelongsToNote) {
                    return ResponseHelper::jsonResponse(false, 'Parent comment does not belong to this note', null, 422);
                }
            }

            $mentionedIds = $validated['mentioned_employee_ids'] ?? [];
            if (! empty($mentionedIds)) {
                // Same pair as the eligibility check above: attendees, plus
                // the note's creator even if they aren't one themselves.
                $allowedMentionIds = $note->attendees()->pluck('employee_profiles.id');
                $creatorEmployeeId = $note->creator?->employeeProfile?->id;
                if ($creatorEmployeeId) {
                    $allowedMentionIds->push($creatorEmployeeId);
                }

                $invalidMentions = collect($mentionedIds)->diff($allowedMentionIds);
                if ($invalidMentions->isNotEmpty()) {
                    return ResponseHelper::jsonResponse(false, 'You can only mention employees checked in as Internal Attendees, or the note\'s creator', null, 422);
                }

                if ($employeeId && in_array($employeeId, $mentionedIds, true)) {
                    return ResponseHelper::jsonResponse(false, 'You can\'t mention yourself', null, 422);
                }
            }

            $comment = $note->comments()->create([
                'parent_id' => $validated['parent_id'] ?? null,
                'user_id' => Auth::id(),
                'body' => $validated['body'],
                'mentioned_employee_ids' => $mentionedIds,
            ]);

            $comment->load(['user', 'parent.user']);

            $this->notifyMentionedEmployees($comment, $mentionedIds);
            $this->logCommentActivity($note, $comment, $user, $mentionedIds);

            return ResponseHelper::jsonResponse(true, 'Comment Added Successfully', new MeetingNoteCommentResource($comment), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $comment = MeetingNoteComment::findOrFail($id);
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
     * @param  array<int>  $mentionedIds
     */
    private function notifyMentionedEmployees(MeetingNoteComment $comment, array $mentionedIds): void
    {
        if (empty($mentionedIds)) {
            return;
        }

        $mentionedUsers = EmployeeProfile::with('user')
            ->whereIn('id', $mentionedIds)
            ->get()
            ->pluck('user')
            ->filter();

        Notification::send($mentionedUsers, new MeetingNoteCommentMention($comment));
    }

    /**
     * @param  array<int>  $mentionedIds
     */
    private function logCommentActivity(MeetingNote $note, MeetingNoteComment $comment, User $user, array $mentionedIds): void
    {
        $description = $comment->parent_id ? 'replied to a comment' : 'commented';

        if (! empty($mentionedIds)) {
            $mentionedNames = EmployeeProfile::with('user')
                ->whereIn('id', $mentionedIds)
                ->get()
                ->pluck('user.name')
                ->filter()
                ->implode(', ');

            if ($mentionedNames) {
                $description .= " and mentioned {$mentionedNames}";
            }
        }

        activity('Meeting Note')
            ->causedBy($user)
            ->performedOn($note)
            ->event('commented')
            ->withProperties([
                'comment_id' => $comment->id,
                'mentioned_employee_ids' => $mentionedIds,
            ])
            ->log("{$description} on \"{$note->title}\"");
    }
}
