<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\DocumentLetterRejectRequest;
use App\Http\Requests\DocumentLetterStoreRequest;
use App\Http\Requests\DocumentLetterUpdateRequest;
use App\Http\Resources\DocumentLetterResource;
use App\Http\Resources\PaginateResource;
use App\Models\DocumentLetter;
use App\Models\DocumentLetterAttachment;
use App\Services\DocumentNumberService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;

class DocumentLetterController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['sender.user', 'creator', 'approver', 'attachments'];

    public function __construct(
        private DocumentNumberService $numberService,
        private EmailService $emailService
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['document-letter-menu|document-letter-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['document-letter-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['document-letter-edit']), only: ['update', 'submit']),
            new Middleware(PermissionMiddleware::using(['document-letter-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['document-letter-approve']), only: ['approve', 'reject']),
        ];
    }

    /**
     * Staff only ever see documents they authored or that were sent to a
     * team they belong to -- never the full company-wide list.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = DocumentLetter::query()->with(self::RELATIONS)->orderByDesc('created_at');

            if ($user->hasRole('staff')) {
                $query->visibleTo($user);
            }

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $documentLetters = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Document Letters Retrieved Successfully', PaginateResource::make($documentLetters, DocumentLetterResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Blocks Staff from creating an Official Memo at the role level, on top
     * of the "document-letter-create" permission gate -- per spec, this is
     * a hard business rule, not just a permission toggle.
     */
    public function store(DocumentLetterStoreRequest $request)
    {
        $user = Auth::user();

        if ($user->hasRole('staff')) {
            return ResponseHelper::jsonResponse(false, 'Staff are not allowed to create an Official Memo.', null, 403);
        }

        $validated = $request->validated();

        try {
            $documentLetter = DB::transaction(function () use ($validated, $user, $request) {
                $date = Carbon::parse($validated['document_date']);
                $documentNumber = $validated['document_number']
                    ?? $this->numberService->generateNotaDinasNumber($date)['number'];

                $documentLetter = DocumentLetter::create([
                    'document_number' => $documentNumber,
                    'subject' => $validated['subject'],
                    'document_date' => $validated['document_date'],
                    'sender_id' => $user->employeeProfile?->id,
                    'body' => $validated['body'],
                    'status' => 'draft',
                    'created_by' => $user->id,
                ]);

                $this->storeAttachments($documentLetter, $request->file('attachments', []));

                return $documentLetter;
            });

            return ResponseHelper::jsonResponse(true, 'Official Memo Created Successfully', new DocumentLetterResource($documentLetter->load(self::RELATIONS)), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $user = Auth::user();
            $query = DocumentLetter::with(self::RELATIONS);

            if ($user->hasRole('staff')) {
                $query->visibleTo($user);
            }

            $documentLetter = $query->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Document Letter Retrieved Successfully', new DocumentLetterResource($documentLetter), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Document Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Only the author may edit their own document, and only while it is
     * still a Draft -- once submitted the form is locked per the workflow.
     */
    public function update(DocumentLetterUpdateRequest $request, string $id)
    {
        try {
            $documentLetter = DocumentLetter::findOrFail($id);

            $guardError = $this->guardEditable($documentLetter);
            if ($guardError) {
                return $guardError;
            }

            $validated = $request->validated();

            $documentLetter = DB::transaction(function () use ($documentLetter, $validated, $request) {
                $documentLetter->update(collect($validated)->only(['document_number', 'subject', 'document_date', 'body'])->toArray());

                foreach ($validated['remove_attachment_ids'] ?? [] as $attachmentId) {
                    $this->deleteAttachment($documentLetter, $attachmentId);
                }

                $this->storeAttachments($documentLetter, $request->file('attachments', []));

                return $documentLetter;
            });

            return ResponseHelper::jsonResponse(true, 'Official Memo Updated Successfully', new DocumentLetterResource($documentLetter->load(self::RELATIONS)), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Document Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $documentLetter = DocumentLetter::findOrFail($id);

            $guardError = $this->guardEditable($documentLetter);
            if ($guardError) {
                return $guardError;
            }

            foreach ($documentLetter->attachments as $attachment) {
                $this->deleteAttachment($documentLetter, $attachment->id);
            }

            $documentLetter->delete();

            return ResponseHelper::jsonResponse(true, 'Official Memo Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Document Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Draft -> Pending. Only the author can submit their own document, and
     * only from Draft. Notifies every Finance Manager once it lands.
     */
    public function submit(string $id)
    {
        try {
            $documentLetter = DocumentLetter::findOrFail($id);

            $guardError = $this->guardEditable($documentLetter);
            if ($guardError) {
                return $guardError;
            }

            $documentLetter->update([
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            $this->emailService->sendDocumentLetterSubmittedNotification($documentLetter);

            return ResponseHelper::jsonResponse(true, 'Official Memo Submitted Successfully', new DocumentLetterResource($documentLetter->load(self::RELATIONS)), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Document Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Pending -> Approved. Hard role check on top of the permission gate,
     * per spec: only Finance Manager may approve, full stop.
     */
    public function approve(string $id)
    {
        $user = Auth::user();

        if (! $user->hasRole('finance')) {
            return ResponseHelper::jsonResponse(false, 'Only Finance Manager can approve an Official Memo.', null, 403);
        }

        try {
            $documentLetter = DocumentLetter::findOrFail($id);

            if ($documentLetter->status !== 'pending') {
                return ResponseHelper::jsonResponse(false, 'Only Official Memos with Pending status can be approved.', null, 422);
            }

            $documentLetter->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->emailService->sendDocumentLetterApprovedNotification($documentLetter);

            return ResponseHelper::jsonResponse(true, 'Official Memo Approved Successfully', new DocumentLetterResource($documentLetter->load(self::RELATIONS)), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Document Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Pending -> Rejected. Same hard role check as approve, plus a
     * mandatory reason.
     */
    public function reject(DocumentLetterRejectRequest $request, string $id)
    {
        $user = Auth::user();

        if (! $user->hasRole('finance')) {
            return ResponseHelper::jsonResponse(false, 'Only Finance Manager can reject an Official Memo.', null, 403);
        }

        try {
            $documentLetter = DocumentLetter::findOrFail($id);

            if ($documentLetter->status !== 'pending') {
                return ResponseHelper::jsonResponse(false, 'Only Official Memos with Pending status can be rejected.', null, 422);
            }

            $documentLetter->update([
                'status' => 'rejected',
                'rejection_reason' => $request->validated('rejection_reason'),
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->emailService->sendDocumentLetterRejectedNotification($documentLetter);

            return ResponseHelper::jsonResponse(true, 'Official Memo Rejected Successfully', new DocumentLetterResource($documentLetter->load(self::RELATIONS)), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Document Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Edit/delete/submit are only valid for the document's own author,
     * and only while it is still a Draft -- Pending/Approved/Rejected are
     * locked, matching the read-only rule in the UI spec.
     */
    private function guardEditable(DocumentLetter $documentLetter)
    {
        $user = Auth::user();

        if ($documentLetter->created_by !== $user->id && ! $user->hasRole('superadmin')) {
            return ResponseHelper::jsonResponse(false, 'You can only edit an Official Memo you created yourself.', null, 403);
        }

        if ($documentLetter->status !== 'draft') {
            return ResponseHelper::jsonResponse(false, 'An Official Memo can only be edited while it is in Draft status.', null, 422);
        }

        return null;
    }

    private function storeAttachments(DocumentLetter $documentLetter, array $files): void
    {
        foreach ($files as $file) {
            $storedPath = $file->store('document-letters', 'public');

            $documentLetter->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'size_file' => $this->formatBytes($file->getSize()),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function deleteAttachment(DocumentLetter $documentLetter, int $attachmentId): void
    {
        $attachment = DocumentLetterAttachment::where('document_letter_id', $documentLetter->id)->find($attachmentId);

        if (! $attachment) {
            return;
        }

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

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
