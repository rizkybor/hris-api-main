<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\MeetingNoteStoreRequest;
use App\Http\Requests\MeetingNoteUpdateRequest;
use App\Http\Resources\MeetingNoteResource;
use App\Http\Resources\PaginateResource;
use App\Models\MeetingNote;
use App\Models\MeetingNoteAttachment;
use App\Models\MeetingNoteViewer;
use App\Models\User;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryResourceType;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class MeetingNoteController extends Controller implements HasMiddleware
{
    /**
     * Meeting Note is deliberately restricted to these four roles, per spec
     * -- not even Super Admin. Permissions gate the routes below, but this
     * list is the server-side source of truth the controller re-checks on
     * every action, so it holds even if a role's permissions drift.
     */
    private const ALLOWED_ROLES = ['manager', 'operational_director', 'hr', 'finance'];

    private const RELATIONS = ['creator.employeeProfile', 'updater', 'attendees.user', 'attachments'];

    public function __construct(
        private DocumentNumberService $numberService,
        private CloudinaryManager $cloudinary
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['meeting-note-menu|meeting-note-list']), only: ['index', 'show', 'pinned']),
            new Middleware(PermissionMiddleware::using(['meeting-note-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['meeting-note-edit']), only: ['update', 'heartbeat']),
            new Middleware(PermissionMiddleware::using(['meeting-note-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['meeting-note-list']), only: ['viewers']),
        ];
    }

    private function assertAllowedRole()
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to Meeting Note.', null, 403);
        }

        return null;
    }

    /**
     * Shared repository: every allowed role sees every note, not just
     * their own -- per spec, they collaborate on the same pool.
     */
    public function index(Request $request)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            $userId = Auth::id();
            $query = MeetingNote::query()
                ->with(self::RELATIONS)
                ->withExists(['pinnedByUsers as is_pinned' => fn ($q) => $q->where('users.id', $userId)])
                ->orderByDesc('meeting_date');

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->meeting_type) {
                $query->where('meeting_type', $request->meeting_type);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $notes = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Meeting Notes Retrieved Successfully', PaginateResource::make($notes, MeetingNoteResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(MeetingNoteStoreRequest $request)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        $validated = $request->validated();
        /** @var User $user */
        $user = Auth::user();

        try {
            $note = DB::transaction(function () use ($validated, $user, $request) {
                $date = Carbon::parse($validated['meeting_date']);
                $documentNumber = $validated['document_number']
                    ?? $this->numberService->generateMeetingNoteNumber($date)['number'];

                $note = MeetingNote::create([
                    'document_number' => $documentNumber,
                    'title' => $validated['title'],
                    'meeting_type' => $validated['meeting_type'],
                    'meeting_date' => $validated['meeting_date'],
                    'body' => $validated['body'] ?? null,
                    'action_items' => $validated['action_items'] ?? [],
                    'external_attendees' => $validated['external_attendees'] ?? [],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                if (! empty($validated['attendee_employee_ids'])) {
                    $note->attendees()->sync($validated['attendee_employee_ids']);
                }

                $this->storeAttachments($note, $request->file('attachments', []));

                return $note;
            });

            return ResponseHelper::jsonResponse(true, 'Meeting Note Created Successfully', new MeetingNoteResource($note->load(self::RELATIONS)), 201);
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
            $userId = Auth::id();
            $note = MeetingNote::with(self::RELATIONS)
                ->withExists(['pinnedByUsers as is_pinned' => fn ($q) => $q->where('users.id', $userId)])
                ->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Meeting Note Retrieved Successfully', new MeetingNoteResource($note), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Any of the 4 allowed roles can edit any note (shared ownership),
     * unlike Official Memo which is locked to the original author.
     */
    public function update(MeetingNoteUpdateRequest $request, string $id)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        $validated = $request->validated();
        /** @var User $user */
        $user = Auth::user();

        try {
            $note = MeetingNote::findOrFail($id);

            $note = DB::transaction(function () use ($note, $validated, $user, $request) {
                $note->update([
                    ...collect($validated)->only(['document_number', 'title', 'meeting_type', 'meeting_date', 'body'])->toArray(),
                    'updated_by' => $user->id,
                ]);

                if (array_key_exists('action_items', $validated)) {
                    $note->update(['action_items' => $validated['action_items'] ?? []]);
                }

                if (array_key_exists('external_attendees', $validated)) {
                    $note->update(['external_attendees' => $validated['external_attendees'] ?? []]);
                }

                if (isset($validated['attendee_employee_ids'])) {
                    $note->attendees()->sync($validated['attendee_employee_ids']);
                }

                foreach ($validated['remove_attachment_ids'] ?? [] as $attachmentId) {
                    $this->deleteAttachment($note, $attachmentId);
                }

                $this->storeAttachments($note, $request->file('attachments', []));

                return $note;
            });

            return ResponseHelper::jsonResponse(true, 'Meeting Note Updated Successfully', new MeetingNoteResource($note->load(self::RELATIONS)), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
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
            $note = MeetingNote::findOrFail($id);

            foreach ($note->attachments as $attachment) {
                $this->deleteAttachment($note, $attachment->id);
            }

            $note->delete();

            return ResponseHelper::jsonResponse(true, 'Meeting Note Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Pin/unpin is a shared, note-level toggle controlled solely by the
     * note's creator -- pinning surfaces it on the Sticky Notes dashboard
     * of every checked-off Internal Attendee too, not just the creator's
     * own. Only the creator (not "any of the 4 allowed roles") may call
     * this, per spec.
     */
    public function togglePin(string $id)
    {
        try {
            $note = MeetingNote::with('attendees.user')->findOrFail($id);
            /** @var User $user */
            $user = Auth::user();

            if ($note->created_by !== $user->id) {
                return ResponseHelper::jsonResponse(false, 'Only the note\'s creator can pin or unpin it.', null, 403);
            }

            $userIds = $note->attendees
                ->pluck('user.id')
                ->filter()
                ->push($user->id)
                ->unique()
                ->values();

            $alreadyPinned = $note->pinnedByUsers()->where('users.id', $user->id)->exists();

            if ($alreadyPinned) {
                $note->pinnedByUsers()->detach($userIds);
            } else {
                $note->pinnedByUsers()->syncWithoutDetaching($userIds);
            }

            return ResponseHelper::jsonResponse(true, $alreadyPinned ? 'Unpinned from Dashboard' : 'Pinned to Dashboard', ['is_pinned' => ! $alreadyPinned], 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Sticky Note widget source: the current user's pinned notes, newest
     * pin first.
     */
    public function pinned()
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            /** @var User $user */
            $user = Auth::user();

            $notes = $user->pinnedMeetingNotes()
                ->with(self::RELATIONS)
                ->orderByDesc('user_pinned_notes.created_at')
                ->get()
                ->each(fn ($note) => $note->is_pinned = true);

            return ResponseHelper::jsonResponse(true, 'Pinned Meeting Notes Retrieved Successfully', MeetingNoteResource::collection($notes), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Presence heartbeat: the detail/edit page pings this every few
     * seconds while open. No WebSocket infra -- "live" here means
     * "recently polled" (see MeetingNote::ACTIVE_VIEWER_WINDOW_SECONDS).
     */
    public function heartbeat(Request $request, string $id)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            $note = MeetingNote::findOrFail($id);

            MeetingNoteViewer::updateOrCreate(
                ['meeting_note_id' => $note->id, 'user_id' => Auth::id()],
                ['is_editing' => (bool) $request->boolean('is_editing'), 'last_seen_at' => now()]
            );

            return ResponseHelper::jsonResponse(true, 'Heartbeat Recorded', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function viewers(string $id)
    {
        if ($guardError = $this->assertAllowedRole()) {
            return $guardError;
        }

        try {
            $note = MeetingNote::findOrFail($id);

            $viewers = $note->viewers()->active()->with('user')->get()->map(fn ($viewer) => [
                'user_id' => $viewer->user_id,
                'name' => $viewer->user?->name,
                'is_editing' => $viewer->is_editing,
                'is_self' => $viewer->user_id === Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Active Viewers Retrieved Successfully', $viewers, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Meeting Note Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function storeAttachments(MeetingNote $note, array $files): void
    {
        foreach ($files as $file) {
            $publicId = $this->cloudinary->uploadAuto(
                $file,
                CloudinaryFolders::companyFiles('meeting-notes'),
                CloudinaryFolders::filename('meeting-note-'.$note->id.'-attachment')
            );

            $note->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $publicId,
                'mime_type' => $file->getClientMimeType(),
                'size_file' => $this->formatBytes($file->getSize()),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function deleteAttachment(MeetingNote $note, int $attachmentId): void
    {
        $attachment = MeetingNoteAttachment::where('meeting_note_id', $note->id)->find($attachmentId);

        if (! $attachment) {
            return;
        }

        $this->cloudinary->delete($attachment->file_path, CloudinaryResourceType::fromMime($attachment->mime_type));

        $attachment->delete();
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
