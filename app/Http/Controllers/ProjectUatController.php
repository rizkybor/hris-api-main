<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProjectUatSessionResource;
use App\Models\Project;
use App\Models\ProjectUatItem;
use App\Models\ProjectUatLocation;
use App\Models\ProjectUatSession;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * UAT (User Acceptance Test) of a project: the number of rounds agreed with
 * the client and each round held (date, status, summary, findings/notes, signed
 * BA UAT). Same permissions as project documents: reading needs any
 * project permission, recording needs project-edit, deleting project-delete.
 */
class ProjectUatController extends Controller implements HasMiddleware
{
    public function __construct(private CloudinaryManager $cloudinary) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['updatePlan', 'store', 'update', 'resolveItem', 'uploadItemPhoto', 'deleteItemPhoto']),
            new Middleware(PermissionMiddleware::using(['project-delete']), only: ['destroy']),
        ];
    }

    public function index(string $projectId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }

        return ResponseHelper::jsonResponse(true, 'Project UAT Retrieved Successfully', $this->payload($project), 200);
    }

    /** Set (or clear) how many UAT rounds were agreed. */
    public function updatePlan(Request $request, string $projectId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }

        $data = $request->validate([
            'uat_rounds_planned' => ['present', 'nullable', 'integer', 'min:1', 'max:'.ProjectUatSession::MAX_ROUNDS],
        ]);
        $project->update(['uat_rounds_planned' => $data['uat_rounds_planned']]);

        return ResponseHelper::jsonResponse(true, 'UAT Plan Updated Successfully', $this->payload($project), 200);
    }

    /** Record the next round (its number is assigned here). */
    public function store(Request $request, string $projectId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }
        if ($project->uatSessions()->count() >= ProjectUatSession::MAX_ROUNDS) {
            return ResponseHelper::jsonResponse(false, 'A project can have at most '.ProjectUatSession::MAX_ROUNDS.' UAT rounds.', null, 422);
        }

        $data = $request->validate($this->rules(), $this->messages());
        $file = $data['attachment'] ?? null;
        [$data, $items, $locations] = $this->splitItems($data);
        unset($data['attachment'], $data['remove_attachment']);

        $session = DB::transaction(function () use ($project, $data, $items, $locations, $request) {
            $round = (int) $project->uatSessions()->lockForUpdate()->max('round') + 1;

            $session = $project->uatSessions()->create($data + [
                'round' => $round,
                'status' => $data['status'] ?? ProjectUatSession::STATUS_SCHEDULED,
                'created_by' => $request->user()?->id,
            ]);
            $places = $this->syncLocations($session, $locations ?? []);
            $this->syncItems($session, $items ?? [], $places);

            return $session;
        });
        if ($file) {
            $this->replaceAttachment($project, $session, $file);
        }

        return ResponseHelper::jsonResponse(true, 'UAT Round Recorded Successfully', $this->payload($project->fresh()), 201);
    }

    /** Sent as POST with _method=PUT when a file is attached. */
    public function update(Request $request, string $projectId, string $sessionId)
    {
        $session = ProjectUatSession::query()->where('project_id', $projectId)->find($sessionId);
        if (! $session) {
            return ResponseHelper::jsonResponse(false, 'UAT Round Not Found', null, 404);
        }

        $rules = $this->rules();
        foreach (['date', 'status', 'notes'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }
        $data = $request->validate($rules, $this->messages());
        $file = $data['attachment'] ?? null;
        $remove = (bool) ($data['remove_attachment'] ?? false);
        [$data, $items, $locations] = $this->splitItems($data);
        unset($data['attachment'], $data['remove_attachment']);

        DB::transaction(function () use ($session, $data, $items, $locations) {
            $session->update($data);
            $places = $locations !== null
                ? $this->syncLocations($session, $locations)
                : $session->locations()->pluck('id')->all();
            if ($items !== null) {
                $this->syncItems($session, $items, $places);
            }
        });
        if ($file) {
            $this->replaceAttachment($session->project, $session, $file);
        } elseif ($remove && $session->attachment_path) {
            $this->cloudinary->delete($session->attachment_path, 'raw');
            $session->update(['attachment_path' => null, 'attachment_name' => null, 'attachment_type' => null, 'attachment_size' => null]);
        }

        return ResponseHelper::jsonResponse(true, 'UAT Round Updated Successfully', $this->payload($session->project), 200);
    }

    /** Later rounds move up so the numbering stays 1, 2, 3 ... */
    public function destroy(string $projectId, string $sessionId)
    {
        $session = ProjectUatSession::query()->where('project_id', $projectId)->find($sessionId);
        if (! $session) {
            return ResponseHelper::jsonResponse(false, 'UAT Round Not Found', null, 404);
        }

        $project = $session->project;
        $path = $session->attachment_path;
        // Items go with the round (cascade); their proof photos are removed below.
        $photos = $session->items()->whereNotNull('photo_path')->pluck('photo_path');
        DB::transaction(function () use ($session, $project) {
            $round = $session->round;
            $session->delete();
            // One by one, in order, so the (project_id, round) unique key holds.
            $project->uatSessions()->where('round', '>', $round)->orderBy('round')->get()
                ->each(fn (ProjectUatSession $later) => $later->update(['round' => $later->round - 1]));
        });
        if ($path) {
            $this->cloudinary->delete($path, 'raw');
        }
        $photos->each(fn ($photo) => $this->cloudinary->delete($photo));

        return ResponseHelper::jsonResponse(true, 'UAT Round Deleted Successfully', $this->payload($project->fresh()), 200);
    }

    private function payload(Project $project): array
    {
        $sessions = $project->uatSessions()->with(['locations', 'items.resolver:id,name'])->orderBy('round')->get();
        $planned = $project->uat_rounds_planned;
        $done = $sessions->whereIn('status', ProjectUatSession::DONE_STATUSES)->count();
        $passed = $sessions->firstWhere('status', ProjectUatSession::STATUS_PASSED);

        return [
            'project_id' => $project->id,
            'planned' => $planned,
            'done' => $done,
            'recorded' => $sessions->count(),
            // Rounds left in the agreement (null when no agreement is set).
            'remaining' => $planned === null ? null : max(0, $planned - $done),
            'over_plan' => $planned !== null && $sessions->count() > $planned,
            'passed_round' => $passed?->round,
            'sessions' => $sessions->map(fn (ProjectUatSession $session) => (new ProjectUatSessionResource($session))->withPlan($planned)->resolve(request()))->values(),
        ];
    }

    /**
     * The round's own fields, and its items: null when the items weren't
     * sent (leave them as they are), a list (possibly empty) when they were.
     *
     * @return array{0: array, 1: ?array, 2: ?array}
     */
    private function splitItems(array $data): array
    {
        $sent = array_key_exists('items', $data) || ! empty($data['items_sent']);
        $items = $sent ? array_values($data['items'] ?? []) : null;
        $locationsSent = array_key_exists('locations', $data) || ! empty($data['locations_sent']);
        $locations = $locationsSent ? array_values($data['locations'] ?? []) : null;
        unset($data['items'], $data['items_sent'], $data['locations'], $data['locations_sent']);

        return [$data, $items, $locations];
    }

    /**
     * Makes the round's findings / notes match the list, in order: items
     * sent with their id are updated (keeping their check-off and photo),
     * new ones are created, and the rest removed (with their photos).
     */
    private function syncItems(ProjectUatSession $session, array $items, array $places = []): void
    {
        $existing = $session->items()->get()->keyBy('id');
        $kept = [];
        foreach ($items as $position => $item) {
            $values = [
                'position' => $position,
                'type' => $item['type'],
                'feature' => filled($item['feature'] ?? null) ? trim($item['feature']) : null,
                'menu' => filled($item['menu'] ?? null) ? trim($item['menu']) : null,
                'description' => trim($item['description']),
                'project_uat_location_id' => isset($item['location_index']) ? ($places[(int) $item['location_index']] ?? null) : null,
            ];
            $model = isset($item['id']) ? $existing->get((int) $item['id']) : null;
            if ($model) {
                $model->update($values);
                $kept[] = $model->id;
            } else {
                $kept[] = $session->items()->create($values)->id;
            }
        }
        $existing->except($kept)->each(function (ProjectUatItem $removed) {
            if ($removed->photo_path) {
                $this->cloudinary->delete($removed->photo_path);
            }
            $removed->delete();
        });
    }

    /**
     * Makes the round's places match the list, in order (same upsert rule
     * as the items). Returns the place ids by list position, so items can
     * point at a place by its index (new places have no id yet).
     *
     * @return list<int>
     */
    private function syncLocations(ProjectUatSession $session, array $locations): array
    {
        $existing = $session->locations()->get()->keyBy('id');
        $ids = [];
        foreach ($locations as $position => $location) {
            $values = [
                'position' => $position,
                'location' => trim($location['location']),
                'held_on' => $location['date'] ?? null,
                'start_time' => filled($location['time'] ?? null) ? $location['time'] : null,
                'participants' => filled($location['participants'] ?? null) ? trim($location['participants']) : null,
            ];
            $model = isset($location['id']) ? $existing->get((int) $location['id']) : null;
            if ($model) {
                $model->update($values);
                $ids[] = $model->id;
            } else {
                $ids[] = $session->locations()->create($values)->id;
            }
        }
        // Items of a removed place stay, just without a place (nullOnDelete).
        $existing->except($ids)->each->delete();

        return $ids;
    }

    /** Check an item off as revised / updated, or undo it. */
    public function resolveItem(Request $request, string $projectId, string $itemId)
    {
        $item = $this->findItem($projectId, $itemId);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'UAT Item Not Found', null, 404);
        }
        $data = $request->validate(['resolved' => ['required', 'boolean']]);
        $item->update($data['resolved']
            ? ['is_resolved' => true, 'resolved_at' => now(), 'resolved_by' => $request->user()?->id]
            : ['is_resolved' => false, 'resolved_at' => null, 'resolved_by' => null]);

        return ResponseHelper::jsonResponse(true, 'UAT Item Updated Successfully', $this->payload($item->session->project), 200);
    }

    /** Optional proof photo: one image per item, kept small. */
    public function uploadItemPhoto(Request $request, string $projectId, string $itemId)
    {
        $item = $this->findItem($projectId, $itemId);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'UAT Item Not Found', null, 404);
        }
        $request->validate(
            ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.ProjectUatItem::PHOTO_MAX_KB]],
            [
                'photo.image' => 'Lampiran harus berupa foto (JPG, PNG, atau WebP).',
                'photo.mimes' => 'Lampiran harus berupa foto (JPG, PNG, atau WebP).',
                'photo.max' => 'Ukuran foto maksimal '.(ProjectUatItem::PHOTO_MAX_KB / 1024).' MB.',
            ]
        );
        $file = $request->file('photo');
        $project = $item->session->project;
        $old = $item->photo_path;
        $publicId = $this->cloudinary->uploadImage(
            $file,
            CloudinaryFolders::projectFiles(),
            CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-uat'.$item->session->round.'-bukti')
        );
        $item->update(['photo_path' => $publicId, 'photo_name' => mb_substr($file->getClientOriginalName(), 0, 255)]);
        if ($old && $old !== $publicId) {
            $this->cloudinary->delete($old);
        }

        return ResponseHelper::jsonResponse(true, 'UAT Item Photo Uploaded Successfully', $this->payload($project), 200);
    }

    public function deleteItemPhoto(string $projectId, string $itemId)
    {
        $item = $this->findItem($projectId, $itemId);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'UAT Item Not Found', null, 404);
        }
        if ($item->photo_path) {
            $this->cloudinary->delete($item->photo_path);
            $item->update(['photo_path' => null, 'photo_name' => null]);
        }

        return ResponseHelper::jsonResponse(true, 'UAT Item Photo Removed Successfully', $this->payload($item->session->project), 200);
    }

    private function findItem(string $projectId, string $itemId): ?ProjectUatItem
    {
        return ProjectUatItem::query()
            ->whereKey($itemId)
            ->whereHas('session', fn ($query) => $query->where('project_id', $projectId))
            ->with('session.project')
            ->first();
    }

    private function replaceAttachment(Project $project, ProjectUatSession $session, UploadedFile $file): void
    {
        $old = $session->attachment_path;
        $publicId = $this->cloudinary->uploadRaw(
            $file,
            CloudinaryFolders::projectFiles(),
            CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-uat'.$session->round)
        );
        $session->update([
            'attachment_path' => $publicId,
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'attachment_type' => strtolower($file->getClientOriginalExtension()),
            'attachment_size' => $this->formatBytes($file->getSize()),
        ]);
        if ($old && $old !== $publicId) {
            $this->cloudinary->delete($old, 'raw');
        }
    }

    private function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', Rule::in(ProjectUatSession::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Findings / notes of the round. The client always sends
            // items_sent=1 so an empty list (all removed) can be told apart
            // from "not sent" (a multipart form can't carry an empty array).
            'items_sent' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'max:100'],
            // An existing item keeps its id (and with it its check-off and photo).
            'items.*.id' => ['nullable', 'integer'],
            'items.*.type' => ['required', Rule::in(ProjectUatItem::TYPES)],
            'items.*.feature' => ['nullable', 'string', 'max:150'],
            'items.*.menu' => ['nullable', 'string', 'max:150'],
            'items.*.description' => ['required', 'string', 'max:2000'],
            // Which place (index into "locations") the item came from.
            'items.*.location_index' => ['nullable', 'integer', 'min:0'],
            // Places held at the same time (optional); locations_sent works
            // like items_sent.
            'locations_sent' => ['nullable', 'boolean'],
            'locations' => ['nullable', 'array', 'max:'.ProjectUatLocation::MAX_PER_ROUND],
            'locations.*.id' => ['nullable', 'integer'],
            'locations.*.location' => ['required', 'string', 'max:150'],
            'locations.*.date' => ['nullable', 'date_format:Y-m-d'],
            'locations.*.time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'locations.*.participants' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'attachment.mimes' => 'The attachment must be a PDF, Word, Excel or image file.',
            'attachment.max' => 'The attachment may not be larger than 10 MB.',
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = min((int) floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);

        return round($bytes / (1 << (10 * $pow)), $precision).' '.$units[$pow];
    }
}
