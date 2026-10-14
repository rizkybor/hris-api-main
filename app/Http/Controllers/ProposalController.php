<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProposalRequest;
use App\Mail\ProposalShareMail;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryUrl;
use App\Services\ProposalPdfService;
use App\Support\HtmlSanitizer;
use App\Support\ProposalTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProposalController extends Controller implements HasMiddleware
{
    public function __construct(protected CloudinaryManager $cloudinary) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['proposal-menu']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['proposal-create']), only: ['store', 'duplicate']),
            new Middleware(PermissionMiddleware::using(['proposal-edit']), only: ['update', 'share', 'unshare', 'shareByEmail']),
            new Middleware(PermissionMiddleware::using(['proposal-create|proposal-edit']), only: ['uploadImage']),
            new Middleware(PermissionMiddleware::using(['proposal-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $proposals = Proposal::with(['creator', 'updater'])
                ->when($request->search, fn ($q) => $q->search($request->search))
                ->when($request->status, fn ($q) => $q->where('status', $request->status))
                ->latest('proposal_date')
                ->latest('id')
                ->paginate($request->row_per_page ?? 10);

            return ResponseHelper::jsonResponse(true, 'Proposals Retrieved Successfully', PaginateResource::make($proposals, ProposalResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(ProposalRequest $request)
    {
        try {
            $data = $request->validated();

            $sections = isset($data['sections'])
                ? $this->cleanSections($data['sections'])
                : ProposalTemplate::sections(['client' => $data['client_name'], 'recipient' => $data['recipient'] ?? null]);

            $proposal = Proposal::create([
                ...collect($data)->except(['sections', 'recipient'])->all(),
                'status' => $data['status'] ?? 'draft',
                'use_signature' => $data['use_signature'] ?? true,
                'signatory_name' => $data['signatory_name'] ?? 'Aldi Pratama Putra, S.I.Kom.',
                'signatory_title' => $data['signatory_title'] ?? 'Direktur Utama',
                'sections' => $sections,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Proposal Created Successfully', (new ProposalResource($proposal->load(['creator', 'updater', 'client'])))->withSections(), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $proposal = Proposal::with(['creator', 'updater', 'client'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Proposal Retrieved Successfully', (new ProposalResource($proposal))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(ProposalRequest $request, int $id)
    {
        try {
            $proposal = Proposal::findOrFail($id);
            $data = $request->validated();

            $proposal->update([
                ...collect($data)->except(['sections', 'recipient'])->all(),
                'sections' => $this->cleanSections($data['sections']),
                'updated_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Proposal Updated Successfully', (new ProposalResource($proposal->load(['creator', 'updater', 'client'])))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            Proposal::findOrFail($id)->delete();

            return ResponseHelper::jsonResponse(true, 'Proposal Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Starts a new proposal from an existing one -- the usual way to write
     * a proposal for a similar project. The copy is a fresh draft dated
     * today, without the original's letter number.
     */
    public function duplicate(int $id)
    {
        try {
            $source = Proposal::findOrFail($id);

            // The share link stays with the original -- a copy starts unshared.
            $copy = $source->replicate(['letter_number', 'status', 'created_by', 'updated_by', 'share_token', 'share_expires_at', 'share_view_count', 'share_last_viewed_at']);
            $copy->title = Str::limit($source->title.' (Salinan)', 255, '');
            $copy->status = 'draft';
            $copy->proposal_date = now('Asia/Jakarta')->toDateString();
            // Fresh section ids, so the copy's sections never share an id
            // with the original's.
            $copy->sections = array_map(fn ($s) => [...$s, 'id' => (string) Str::uuid()], $source->sections ?? []);
            $copy->created_by = Auth::id();
            $copy->updated_by = Auth::id();
            $copy->save();

            return ResponseHelper::jsonResponse(true, 'Proposal Duplicated Successfully', (new ProposalResource($copy->load(['creator', 'updater', 'client'])))->withSections(), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /** Images placed inside a proposal (diagrams, schedules) or as its cover. */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        try {
            $publicId = $this->cloudinary->uploadImage(
                $request->file('image'),
                CloudinaryFolders::companyFiles('proposals'),
                CloudinaryFolders::filename('proposal-image')
            );

            return ResponseHelper::jsonResponse(true, 'Image Uploaded Successfully', [
                'public_id' => $publicId,
                'url' => CloudinaryUrl::image($publicId),
            ], 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Image upload failed: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(int $id, ProposalPdfService $pdfService)
    {
        return $pdfService->response(Proposal::findOrFail($id));
    }

    /**
     * Creates (or keeps) the proposal's public share link and sets how long
     * it stays valid. Re-sharing an already shared proposal keeps the same
     * URL, so a link already sent to the client keeps working.
     */
    public function share(Request $request, int $id)
    {
        $data = $request->validate([
            'expires_in_days' => ['nullable', 'integer', Rule::in([7, 14, 30])],
        ]);

        try {
            $proposal = Proposal::findOrFail($id);
            $proposal->forceFill([
                'share_token' => $proposal->share_token ?? Str::random(48),
                'share_expires_at' => isset($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null,
            ])->save();

            return ResponseHelper::jsonResponse(true, 'Share Link Ready', (new ProposalResource($proposal->load(['creator', 'updater', 'client'])))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Emails the proposal's share link to the client (creating the link if
     * needed, or renewing one that has expired). A draft is marked as sent.
     */
    public function shareByEmail(Request $request, int $id)
    {
        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'array', 'max:5'],
            'cc.*' => ['email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'expires_in_days' => ['nullable', 'integer', Rule::in([7, 14, 30])],
        ], [], ['to' => 'Email Penerima', 'cc.*' => 'Email CC', 'message' => 'Pesan']);

        try {
            $proposal = Proposal::findOrFail($id);

            if (! $proposal->isShareActive() || array_key_exists('expires_in_days', $data)) {
                $proposal->forceFill([
                    'share_token' => $proposal->isShareActive() ? $proposal->share_token : Str::random(48),
                    'share_expires_at' => isset($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null,
                ]);
            }
            if ($proposal->status === 'draft') {
                $proposal->status = 'sent';
            }
            $proposal->save();

            Mail::to($data['to'])
                ->cc($data['cc'] ?? [])
                ->send(new ProposalShareMail($proposal, $data['recipient_name'] ?? null, $data['message'] ?? null));

            activity('Business Documents')
                ->performedOn($proposal)
                ->withProperties(['to' => $data['to'], 'cc' => $data['cc'] ?? []])
                ->event('shared_by_email')
                ->log('sent a proposal share link by email');

            return ResponseHelper::jsonResponse(true, 'Proposal Sent', (new ProposalResource($proposal->load(['creator', 'updater', 'client'])))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Email gagal dikirim: '.$e->getMessage(), null, 500);
        }
    }

    /** Turns the share link off; sharing again later issues a new URL. */
    public function unshare(int $id)
    {
        try {
            $proposal = Proposal::findOrFail($id);
            $proposal->forceFill([
                'share_token' => null,
                'share_expires_at' => null,
                'share_view_count' => 0,
                'share_last_viewed_at' => null,
            ])->save();

            return ResponseHelper::jsonResponse(true, 'Share Link Disabled', (new ProposalResource($proposal->load(['creator', 'updater', 'client'])))->withSections(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Proposal Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function cleanSections(array $sections): array
    {
        return array_values(array_map(fn ($section) => [
            'id' => (string) $section['id'],
            'title' => trim((string) ($section['title'] ?? '')),
            'content' => HtmlSanitizer::clean($section['content'] ?? ''),
            'page_break_before' => (bool) ($section['page_break_before'] ?? false),
        ], $sections));
    }
}
