<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProjectUatSessionResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUatItem;
use App\Models\ProjectUatLocation;
use App\Models\ProjectUatSession;
use App\Services\BeritaAcaraService;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\CoverLetterService;
use App\Services\Pdf\LetterPdfService;
use App\Services\Pdf\UatReportService;
use App\Services\UatTaskSync;
use Carbon\Carbon;
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
            new Middleware(PermissionMiddleware::using(['project-list|project-create|project-edit|project-delete']), only: ['index', 'locationItems', 'beritaAcaraOptions', 'beritaAcaraPdf']),
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['generateBeritaAcara']),
            new Middleware(PermissionMiddleware::using(['project-export']), only: ['exportOptions', 'export']),
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['updatePlan', 'store', 'update', 'storeItem', 'updateItem', 'destroyItem', 'resolveItem', 'uploadItemPhoto', 'deleteItemPhoto']),
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

    /**
     * Export dialog data for one UAT document (kind = result | revision):
     * earlier cover letters, divisions, signatories and a drafted letter.
     */
    public function exportOptions(Request $request, string $projectId, string $sessionId, CoverLetterService $covers)
    {
        $uat = $this->findUat($projectId, $sessionId);
        if (! $uat) {
            return ResponseHelper::jsonResponse(false, 'UAT Round Not Found', null, 404);
        }
        $kind = $request->validate(['kind' => ['required', Rule::in(UatReportService::KINDS)]])['kind'];

        return ResponseHelper::jsonResponse(true, 'UAT Export Options Retrieved Successfully', $covers->options($uat->project, $request->user(), [
            'subject' => UatReportService::title($uat, $kind).' – '.$uat->project->name,
            'body' => $this->coverBody($uat, $kind),
        ]), 200);
    }

    /**
     * The UAT result or revision document, optionally behind a cover letter
     * (Surat Pengantar), as one PDF for the client.
     */
    public function export(Request $request, string $projectId, string $sessionId, UatReportService $reports, CoverLetterService $covers)
    {
        $uat = $this->findUat($projectId, $sessionId);
        if (! $uat) {
            return ResponseHelper::jsonResponse(false, 'UAT Round Not Found', null, 404);
        }
        $data = $request->validate($covers->rules() + ['kind' => ['required', Rule::in(UatReportService::KINDS)]]);
        $kind = $data['kind'];
        $letter = $covers->resolve($data, $uat->project, $request->user(), '1 (satu) berkas '.UatReportService::title($uat, $kind));

        [$pdf, $temp] = $reports->make($uat, $kind);
        try {
            $bytes = $pdf->output();
        } finally {
            foreach ($temp as $file) {
                @unlink($file);
            }
        }

        return $covers->respond($bytes, $letter, UatReportService::filename($uat, $kind), UatReportService::title($uat, $kind).' '.$uat->project->name);
    }

    private function findUat(string $projectId, string $sessionId): ?ProjectUatSession
    {
        return ProjectUatSession::query()->where('project_id', $projectId)
            ->with(['project.client', 'locations.items', 'beritaAcara'])
            ->find($sessionId);
    }

    /** Indonesian cover letter text for a UAT result / revision document. */
    private function coverBody(ProjectUatSession $uat, string $kind): string
    {
        $fmt = fn ($date) => $date ? Carbon::parse($date)->locale('id')->translatedFormat('d F Y') : '-';
        $items = $uat->locations->flatMap(fn ($location) => $location->items);
        $findings = $items->where('type', ProjectUatItem::TYPE_FINDING)->count();
        $notes = $items->where('type', ProjectUatItem::TYPE_NOTE)->count();
        $resolved = $items->where('is_resolved', true)->count();
        $places = $uat->locations->map(fn ($place, $i) => e($place->location ?: 'Sesi '.($i + 1)))->implode(', ');
        $name = '<strong>'.e($uat->project->name).'</strong>';

        if ($kind === 'revision') {
            $rows = [
                'Proyek' => e($uat->project->name),
                'UAT' => 'Ke-'.$uat->round.', '.$fmt($uat->date),
                'Jumlah temuan & catatan' => $items->count().' ('.$findings.' temuan, '.$notes.' catatan)',
                'Sudah direvisi / diupdate' => $resolved.' dari '.$items->count(),
                'Masih dalam proses' => (string) ($items->count() - $resolved),
            ];

            return '<p>Dengan hormat,</p>'
                .'<p>Menindaklanjuti hasil User Acceptance Test (UAT) ke-'.$uat->round.' proyek '.$name.', bersama surat ini kami sampaikan laporan hasil revisi atas temuan dan catatan tersebut, dengan ringkasan sebagai berikut:</p>'
                .CoverLetterService::table($rows)
                .'<p>Rincian setiap revisi beserta foto buktinya dapat Bapak/Ibu lihat pada laporan terlampir. Kami mohon kesediaan Bapak/Ibu untuk memeriksa kembali hasil revisi tersebut.</p>'
                .'<p>Demikian surat pengantar ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>';
        }

        $rows = [
            'Proyek' => e($uat->project->name),
            'UAT' => 'Ke-'.$uat->round.', '.$fmt($uat->date),
            'Sesi / lokasi' => $uat->locations->count().' sesi'.($places ? ' ('.$places.')' : ''),
            'Jumlah temuan' => (string) $findings,
            'Jumlah catatan' => (string) $notes,
        ];

        return '<p>Dengan hormat,</p>'
            .'<p>Bersama surat ini, kami sampaikan Laporan Hasil User Acceptance Test (UAT) ke-'.$uat->round.' proyek '.$name.' yang telah dilaksanakan pada '.$fmt($uat->date).', dengan ringkasan sebagai berikut:</p>'
            .CoverLetterService::table($rows)
            .'<p>Seluruh temuan dan catatan tercantum pada laporan terlampir dan akan segera kami tindak lanjuti. Hasil revisinya akan kami sampaikan kepada Bapak/Ibu setelah selesai.</p>'
            .'<p>Demikian surat pengantar ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>';
    }

    // ------------------------------------------------------------ berita acara

    /** Draft for the Berita Acara dialog (and the existing one, if any). */
    public function beritaAcaraOptions(Request $request, string $projectId, string $sessionId, BeritaAcaraService $service)
    {
        $uat = $this->findUat($projectId, $sessionId);
        if (! $uat) {
            return ResponseHelper::jsonResponse(false, 'UAT Round Not Found', null, 404);
        }

        return ResponseHelper::jsonResponse(true, 'Berita Acara Options Retrieved Successfully', $service->options($uat, $request->user()), 200);
    }

    /** Generate the UAT's Berita Acara (a numbered BA letter), or refresh it. */
    public function generateBeritaAcara(Request $request, string $projectId, string $sessionId, BeritaAcaraService $service)
    {
        $uat = $this->findUat($projectId, $sessionId);
        if (! $uat) {
            return ResponseHelper::jsonResponse(false, 'UAT Round Not Found', null, 404);
        }
        abort_unless($request->user()->can('letter-create'), 403, 'You do not have permission to create letters.');
        $letter = $service->generate($uat, $request->validate($service->rules()), $request->user()->id);

        return ResponseHelper::jsonResponse(true, 'Berita Acara Generated Successfully', [
            'letter' => ['id' => $letter->id, 'letter_number' => $letter->letter_number],
            'uat' => $this->payload($uat->project),
        ], 200);
    }

    /** The Berita Acara as PDF (company letterhead, both parties' signatures). */
    public function beritaAcaraPdf(string $projectId, string $sessionId, LetterPdfService $letters)
    {
        $uat = $this->findUat($projectId, $sessionId);
        $letter = $uat?->beritaAcara;
        if (! $letter) {
            return ResponseHelper::jsonResponse(false, 'Berita Acara Not Found', null, 404);
        }

        return $letters->make($letter)->stream(LetterPdfService::filename($letter));
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
        [$data, $locations] = $this->splitLocations($data);
        unset($data['attachment'], $data['remove_attachment']);

        $session = DB::transaction(function () use ($project, $data, $locations, $request) {
            $round = (int) $project->uatSessions()->lockForUpdate()->max('round') + 1;

            $session = $project->uatSessions()->create($data + [
                'round' => $round,
                'status' => $data['status'] ?? ProjectUatSession::STATUS_SCHEDULED,
                'created_by' => $request->user()?->id,
            ]);
            // Every UAT has at least one session.
            $this->syncLocations($session, $locations ?: [['date' => $session->date?->format('Y-m-d')]]);

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
        [$data, $locations] = $this->splitLocations($data);
        unset($data['attachment'], $data['remove_attachment']);

        DB::transaction(function () use ($session, $data, $locations) {
            $session->update($data);
            if ($locations !== null) {
                $this->syncLocations($session, $locations ?: [['date' => $session->date?->format('Y-m-d')]]);
            }
        });
        if ($file) {
            $this->replaceAttachment($session->project, $session, $file);
        } elseif ($remove && $session->attachment_path) {
            $this->cloudinary->delete($session->attachment_path, 'raw');
            $session->update(['attachment_path' => null, 'attachment_name' => null, 'attachment_type' => null, 'attachment_size' => null]);
        }

        // Round number / session names appear in the board tasks.
        app(UatTaskSync::class)->syncProject($session->project_id);

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
        // Items go with the round (cascade); their files are removed below.
        $files = $session->items()->get(['photo_path', 'photo_kind', 'evidence_path', 'evidence_kind']);
        DB::transaction(function () use ($session, $project) {
            $round = $session->round;
            // Their board tasks too (the items go by cascade, without events).
            ProjectTask::withTrashed()->whereIn('project_uat_item_id', $session->items()->select('id'))->forceDelete();
            $session->delete();
            // One by one, in order, so the (project_id, round) unique key holds.
            $project->uatSessions()->where('round', '>', $round)->orderBy('round')->get()
                ->each(fn (ProjectUatSession $later) => $later->update(['round' => $later->round - 1]));
        });
        if ($path) {
            $this->cloudinary->delete($path, 'raw');
        }
        $files->each(function ($item) {
            $this->deleteFile($item->photo_path, $item->photo_kind);
            $this->deleteFile($item->evidence_path, $item->evidence_kind);
        });
        // The round's tasks went with its items; later rounds were renumbered.
        app(UatTaskSync::class)->syncProject($project->id);

        return ResponseHelper::jsonResponse(true, 'UAT Round Deleted Successfully', $this->payload($project->fresh()), 200);
    }

    private function payload(Project $project): array
    {
        $sessions = $project->uatSessions()->with(['locations.items.resolver:id,name', 'beritaAcara'])->orderBy('round')->get();
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
     * The UAT's own fields, and its sessions: null when not sent (leave
     * them), a list when sent (locations_sent=1 marks an emptied list).
     *
     * @return array{0: array, 1: ?array}
     */
    private function splitLocations(array $data): array
    {
        $sent = array_key_exists('locations', $data) || ! empty($data['locations_sent']);
        $locations = $sent ? array_values($data['locations'] ?? []) : null;
        unset($data['locations'], $data['locations_sent']);

        return [$data, $locations];
    }

    /**
     * Makes the UAT's sessions match the list, in order: sessions sent with
     * their id are updated (keeping their findings/notes), new ones created,
     * and the rest removed together with their findings/notes and photos.
     */
    private function syncLocations(ProjectUatSession $session, array $locations): void
    {
        $existing = $session->locations()->get()->keyBy('id');
        $kept = [];
        foreach ($locations as $position => $location) {
            $values = [
                'position' => $position,
                'location' => filled($location['location'] ?? null) ? trim($location['location']) : null,
                'held_on' => $location['date'] ?? null,
                'start_time' => filled($location['time'] ?? null) ? $location['time'] : null,
                'participants' => filled($location['participants'] ?? null) ? trim($location['participants']) : null,
            ];
            $model = isset($location['id']) ? $existing->get((int) $location['id']) : null;
            if ($model) {
                $model->update($values);
                $kept[] = $model->id;
            } else {
                $kept[] = $session->locations()->create($values)->id;
            }
        }
        $existing->except($kept)->each(function (ProjectUatLocation $removed) {
            $removed->items()->get()->each(fn (ProjectUatItem $item) => $this->deleteItemModel($item));
            $removed->delete();
        });
    }

    // ------------------------------------------------------------ findings & notes

    /** Add a finding or note straight to a session (no need to edit the UAT). */
    public function storeItem(Request $request, string $projectId, string $locationId)
    {
        $location = ProjectUatLocation::query()
            ->whereKey($locationId)
            ->whereHas('session', fn ($query) => $query->where('project_id', $projectId))
            ->with('session.project')
            ->first();
        if (! $location) {
            return ResponseHelper::jsonResponse(false, 'UAT Session Not Found', null, 404);
        }
        $data = $request->validate($this->itemRules(), $this->fileMessages('attachment'));
        $item = $location->session->items()->create($this->itemValues($data) + [
            'project_uat_location_id' => $location->id,
            'position' => (int) $location->items()->max('position') + 1,
        ]);
        if ($request->hasFile('attachment')) {
            $this->replaceEvidence($item, $request->file('attachment'));
        }

        return ResponseHelper::jsonResponse(true, 'UAT Item Added Successfully', $this->payload($location->session->project), 201);
    }

    /** A page of a session's findings/notes (the UAT payload carries only the first few). */
    public function locationItems(Request $request, string $projectId, string $locationId)
    {
        $data = $request->validate([
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $location = ProjectUatLocation::query()
            ->whereKey($locationId)
            ->whereHas('session', fn ($query) => $query->where('project_id', $projectId))
            ->first();
        if (! $location) {
            return ResponseHelper::jsonResponse(false, 'UAT Session Not Found', null, 404);
        }
        $offset = (int) ($data['offset'] ?? 0);
        $limit = (int) ($data['limit'] ?? 10);
        $items = $location->items()->with('resolver:id,name')->skip($offset)->take($limit)->get();
        $total = $location->items()->count();

        return ResponseHelper::jsonResponse(true, 'UAT Items Retrieved Successfully', [
            'items' => $items->map(fn (ProjectUatItem $item) => ProjectUatSessionResource::item($item))->values(),
            'offset' => $offset,
            'total' => $total,
            'has_more' => $offset + $items->count() < $total,
        ], 200);
    }

    /** Edit a finding/note; location_id moves it to another session of the same UAT. */
    public function updateItem(Request $request, string $projectId, string $itemId)
    {
        $item = $this->findItem($projectId, $itemId);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'UAT Item Not Found', null, 404);
        }
        $data = $request->validate($this->itemRules() + ['location_id' => ['sometimes', 'integer'], 'remove_attachment' => ['nullable', 'boolean']], $this->fileMessages('attachment'));
        $values = $this->itemValues($data);
        if (isset($data['location_id'])) {
            $target = $item->session->locations()->whereKey($data['location_id'])->first();
            if (! $target) {
                return ResponseHelper::jsonResponse(false, 'UAT Session Not Found', null, 404);
            }
            if ($target->id !== $item->project_uat_location_id) {
                $values['project_uat_location_id'] = $target->id;
                $values['position'] = (int) $target->items()->max('position') + 1;
            }
        }
        $item->update($values);
        if ($request->hasFile('attachment')) {
            $this->replaceEvidence($item, $request->file('attachment'));
        } elseif (! empty($data['remove_attachment']) && $item->evidence_path) {
            $this->deleteFile($item->evidence_path, $item->evidence_kind);
            $item->update(['evidence_path' => null, 'evidence_name' => null, 'evidence_kind' => null]);
        }

        return ResponseHelper::jsonResponse(true, 'UAT Item Updated Successfully', $this->payload($item->session->project), 200);
    }

    public function destroyItem(string $projectId, string $itemId)
    {
        $item = $this->findItem($projectId, $itemId);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'UAT Item Not Found', null, 404);
        }
        $project = $item->session->project;
        $this->deleteItemModel($item);

        return ResponseHelper::jsonResponse(true, 'UAT Item Deleted Successfully', $this->payload($project), 200);
    }

    private function deleteItemModel(ProjectUatItem $item): void
    {
        $this->deleteFile($item->photo_path, $item->photo_kind);
        $this->deleteFile($item->evidence_path, $item->evidence_kind);
        $item->delete();
    }

    /**
     * Item files (issue evidence, revision proof): images go up as images
     * (the browser already shrank them), PDFs as raw files.
     *
     * @return array{0: string, 1: string} public id and kind (image | file)
     */
    private function uploadItemFile(ProjectUatItem $item, UploadedFile $file, string $suffix): array
    {
        $project = $item->session->project;
        $name = CloudinaryFolders::filename(CloudinaryFolders::projectPrefix($project->name, $project->id).'-uat'.$item->session->round.'-'.$suffix);
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf' || $file->getMimeType() === 'application/pdf';

        return $isPdf
            ? [$this->cloudinary->uploadRaw($file, CloudinaryFolders::projectFiles(), $name), 'file']
            : [$this->cloudinary->uploadImage($file, CloudinaryFolders::projectFiles(), $name), 'image'];
    }

    private function deleteFile(?string $path, ?string $kind): void
    {
        if ($path) {
            $this->cloudinary->delete($path, $kind === 'file' ? 'raw' : 'image');
        }
    }

    private function replaceEvidence(ProjectUatItem $item, UploadedFile $file): void
    {
        [$path, $kind] = $this->uploadItemFile($item, $file, 'temuan');
        $this->deleteFile($item->evidence_path, $item->evidence_kind);
        $item->update(['evidence_path' => $path, 'evidence_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'evidence_kind' => $kind]);
    }

    private function fileMessages(string $field): array
    {
        return [
            $field.'.mimes' => 'Lampiran harus berupa foto (JPG, PNG, WebP) atau PDF.',
            $field.'.max' => 'Ukuran lampiran maksimal '.(ProjectUatItem::PHOTO_MAX_KB / 1024).' MB.',
        ];
    }

    private function itemRules(): array
    {
        return [
            'type' => ['required', Rule::in(ProjectUatItem::TYPES)],
            'feature' => ['nullable', 'string', 'max:150'],
            'menu' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            // Optional file showing the issue: image or PDF, kept small.
            'attachment' => ['nullable', 'file', 'mimes:'.ProjectUatItem::FILE_MIMES, 'max:'.ProjectUatItem::PHOTO_MAX_KB],
        ];
    }

    private function itemValues(array $data): array
    {
        return [
            'type' => $data['type'],
            'feature' => filled($data['feature'] ?? null) ? trim($data['feature']) : null,
            'menu' => filled($data['menu'] ?? null) ? trim($data['menu']) : null,
            'description' => trim($data['description']),
        ];
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
        // Revision proof: a photo/screenshot or a PDF, kept small.
        $request->validate(
            ['photo' => ['required', 'file', 'mimes:'.ProjectUatItem::FILE_MIMES, 'max:'.ProjectUatItem::PHOTO_MAX_KB]],
            $this->fileMessages('photo')
        );
        $file = $request->file('photo');
        $project = $item->session->project;
        [$path, $kind] = $this->uploadItemFile($item, $file, 'revisi');
        $this->deleteFile($item->photo_path, $item->photo_kind);
        $item->update(['photo_path' => $path, 'photo_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'photo_kind' => $kind]);

        return ResponseHelper::jsonResponse(true, 'UAT Item Photo Uploaded Successfully', $this->payload($project), 200);
    }

    public function deleteItemPhoto(string $projectId, string $itemId)
    {
        $item = $this->findItem($projectId, $itemId);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'UAT Item Not Found', null, 404);
        }
        if ($item->photo_path) {
            $this->deleteFile($item->photo_path, $item->photo_kind);
            $item->update(['photo_path' => null, 'photo_name' => null, 'photo_kind' => 'image']);
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
            // Sessions of the UAT (same time, different places). The client
            // sends locations_sent=1 so an emptied list can be told apart
            // from "not sent" (a multipart form can't carry an empty array).
            'locations_sent' => ['nullable', 'boolean'],
            'locations' => ['nullable', 'array', 'max:'.ProjectUatLocation::MAX_PER_ROUND],
            'locations.*.id' => ['nullable', 'integer'],
            'locations.*.location' => ['nullable', 'string', 'max:150'],
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
