<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProjectUatSessionResource;
use App\Models\Project;
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
 * the client and each round held (date, status, notes, findings, signed
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
            new Middleware(PermissionMiddleware::using(['project-edit']), only: ['updatePlan', 'store', 'update']),
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
        unset($data['attachment'], $data['remove_attachment']);

        $session = DB::transaction(function () use ($project, $data, $request) {
            $round = (int) $project->uatSessions()->lockForUpdate()->max('round') + 1;

            return $project->uatSessions()->create($data + [
                'round' => $round,
                'status' => $data['status'] ?? ProjectUatSession::STATUS_SCHEDULED,
                'created_by' => $request->user()?->id,
            ]);
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
        foreach (['date', 'status', 'notes', 'findings'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }
        $data = $request->validate($rules, $this->messages());
        $file = $data['attachment'] ?? null;
        $remove = (bool) ($data['remove_attachment'] ?? false);
        unset($data['attachment'], $data['remove_attachment']);

        $session->update($data);
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

        return ResponseHelper::jsonResponse(true, 'UAT Round Deleted Successfully', $this->payload($project->fresh()), 200);
    }

    private function payload(Project $project): array
    {
        $sessions = $project->uatSessions()->orderBy('round')->get();
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
            'findings' => ['nullable', 'string', 'max:20000'],
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
