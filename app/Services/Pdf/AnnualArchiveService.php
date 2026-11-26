<?php

namespace App\Services\Pdf;

use App\Http\Controllers\DocumentLetterController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MeetingNoteController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PurchaseOrderController;
use App\Models\Certificate;
use App\Models\DocumentLetter;
use App\Models\Invoice;
use App\Models\Letter;
use App\Models\MeetingNote;
use App\Models\PaymentReceipt;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\ProposalPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/**
 * Annual archive for the company's yearly report: every document of one
 * type issued in a year, as ONE PDF (a cover with a table of contents, then
 * each document exactly as it downloads on its own page, in date order).
 * Cancelled / unapproved / draft documents are left out.
 */
class AnnualArchiveService
{
    public const TIMEZONE = 'Asia/Jakarta';

    /** Date columns that hold a moment in time (UTC), not a calendar date. */
    private const TIMESTAMP_COLUMNS = ['created_at', 'meeting_date'];

    public function __construct(private PdfMerger $merger) {}

    /**
     * Type key => label, the permissions that may read it (any of), the date
     * column, and how a row reads in the table of contents.
     */
    public function types(): array
    {
        return [
            'letters' => [
                'label' => 'Surat',
                'permissions' => ['letter-menu', 'letter-list'],
                'query' => fn () => Letter::query()->with('letterCode')->where('status', 'issued'),
                'date' => 'date',
                'row' => fn (Letter $l) => [$l->letter_number, $l->subject, $l->letterCode?->name],
            ],
            'official_memos' => [
                'label' => 'Nota Dinas',
                'permissions' => ['document-letter-menu', 'document-letter-list'],
                'query' => fn () => DocumentLetter::query()->where('status', 'approved'),
                'date' => 'document_date',
                'row' => fn (DocumentLetter $d) => [$d->document_number, $d->subject, null],
            ],
            'meeting_notes' => [
                'label' => 'Notulen Rapat',
                'permissions' => ['meeting-note-menu', 'meeting-note-list'],
                // Meeting notes are limited to these roles (not even superadmin).
                'roles' => MeetingNoteController::ALLOWED_ROLES,
                'query' => fn () => MeetingNote::query(),
                'date' => 'meeting_date',
                'row' => fn (MeetingNote $m) => [$m->document_number, $m->title, null],
            ],
            'invoices' => [
                'label' => 'Invoice',
                'permissions' => ['invoice-menu', 'invoice-list'],
                'query' => fn () => Invoice::query()->where('status', '!=', 'cancelled'),
                'date' => 'date',
                'row' => fn (Invoice $i) => [$i->invoice_number, $i->client_name, 'Rp '.number_format((float) $i->total, 0, ',', '.')],
            ],
            'payment_receipts' => [
                'label' => 'Kuitansi',
                'permissions' => ['payment-receipt-menu', 'payment-receipt-list'],
                'query' => fn () => PaymentReceipt::query()->where('status', 'active'),
                'date' => 'date',
                'row' => fn (PaymentReceipt $r) => [$r->receipt_number, $r->received_from, 'Rp '.number_format((float) $r->amount, 0, ',', '.')],
            ],
            'purchase_orders' => [
                'label' => 'Purchase Order',
                'permissions' => ['purchase-order-menu', 'purchase-order-list'],
                'query' => fn () => PurchaseOrder::query()->where('status', 'active'),
                'date' => 'date',
                'row' => fn (PurchaseOrder $p) => [$p->po_number, $p->title, $p->client_name],
            ],
            'proposals' => [
                'label' => 'Proposal Penawaran',
                'permissions' => ['proposal-menu'],
                'query' => fn () => Proposal::query()->where('status', '!=', 'draft'),
                'date' => 'proposal_date',
                'row' => fn (Proposal $p) => [$p->letter_number, $p->title, $p->client_name],
            ],
            'certificates' => [
                'label' => 'Sertifikat',
                'permissions' => ['certificate-menu', 'certificate-list'],
                'query' => fn () => Certificate::query()->whereNotNull('pdf_path'),
                'date' => 'created_at',
                'row' => fn (Certificate $c) => [$c->certificate_number, $c->recipient_name, $c->title],
            ],
        ];
    }

    public function allowedTypes(User $user): array
    {
        return array_keys(array_filter($this->types(), function ($type) use ($user) {
            if (isset($type['roles']) && ! $user->hasAnyRole($type['roles'])) {
                return false;
            }
            foreach ($type['permissions'] as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** The year's documents of one type, oldest first. */
    public function documents(string $type, int $year): Builder
    {
        $config = $this->types()[$type];
        $column = $config['date'];
        $query = ($config['query'])();

        if (in_array($column, self::TIMESTAMP_COLUMNS, true)) {
            // Stored in UTC: 1 Jan 00:00 - 31 Dec 23:59:59 WIB.
            $start = Carbon::create($year, 1, 1, 0, 0, 0, self::TIMEZONE);
            $query->whereBetween($column, [$start->copy()->utc(), $start->copy()->endOfYear()->utc()]);
        } else {
            // Plain dates (may be stored with a 00:00:00 time part).
            $query->whereDate($column, '>=', $year.'-01-01')->whereDate($column, '<=', $year.'-12-31');
        }

        return $query->orderBy($column)->orderBy('id');
    }

    public function count(string $type, int $year): int
    {
        return $this->documents($type, $year)->count();
    }

    /**
     * Years that have any document, newest first (for the year picker).
     *
     * @return list<int>
     */
    public function years(array $types): array
    {
        $years = [];
        foreach ($types as $type) {
            $config = $this->types()[$type];
            $first = ($config['query'])()->min($config['date']);
            $last = ($config['query'])()->max($config['date']);
            if ($first && $last) {
                $years = array_merge($years, range((int) Carbon::parse($first)->tz(self::TIMEZONE)->year, (int) Carbon::parse($last)->tz(self::TIMEZONE)->year));
            }
        }
        $years[] = (int) now(self::TIMEZONE)->year;
        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }

    /**
     * One PDF for one type and year. Returns [bytes, documentCount,
     * skippedCount]; documents whose PDF can't be made are listed on the
     * cover as skipped instead of failing the whole archive.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    public function build(string $type, int $year): array
    {
        $config = $this->types()[$type];
        $documents = $this->documents($type, $year)->get();

        $files = [];
        $rows = [];
        $skipped = [];
        try {
            foreach ($documents as $document) {
                [$number, $title, $extra] = ($config['row'])($document);
                $date = Carbon::parse($document->{$config['date']})->tz(self::TIMEZONE);
                $bytes = $this->render($type, $document);
                if ($bytes === null) {
                    $skipped[] = ['number' => $number ?: '#'.$document->id, 'title' => $title];

                    continue;
                }
                $path = tempnam(sys_get_temp_dir(), 'archive-');
                file_put_contents($path, $bytes);
                $files[] = $path;
                $rows[] = ['number' => $number ?: '—', 'date' => $date, 'title' => $title, 'extra' => $extra, 'pages' => $this->pageCount($path)];
            }

            $cover = $this->cover($config['label'], $year, $rows, $skipped);

            return [$this->merger->mergeFiles(array_merge([$cover['path']], $files), 'Arsip '.$config['label'].' '.$year), count($rows), count($skipped)];
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
            if (isset($cover['path'])) {
                @unlink($cover['path']);
            }
        }
    }

    public function filename(string $type, int $year): string
    {
        return 'Arsip-'.$year.'-'.str_replace(' ', '-', $this->types()[$type]['label']).'.pdf';
    }

    /** The document's own PDF, exactly as its download button gives it. */
    private function render(string $type, Model $document): ?string
    {
        try {
            $response = match ($type) {
                'letters' => app(LetterPdfService::class)->make($document)->output(),
                'proposals' => app(ProposalPdfService::class)->response($document),
                'certificates' => app(CertificateService::class)->downloadBytes($document),
                'official_memos' => app()->call([app(DocumentLetterController::class), 'exportPdf'], ['id' => (string) $document->id]),
                'meeting_notes' => app()->call([app(MeetingNoteController::class), 'exportPdf'], ['id' => (string) $document->id]),
                'invoices' => app()->call([app(InvoiceController::class), 'exportPdf'], ['id' => (string) $document->id]),
                'payment_receipts' => app()->call([app(PaymentReceiptController::class), 'exportPdf'], ['id' => (string) $document->id]),
                'purchase_orders' => app()->call([app(PurchaseOrderController::class), 'exportPdf'], ['id' => (string) $document->id]),
            };
            $bytes = is_string($response) ? $response : $response->getContent();

            return str_starts_with((string) $bytes, '%PDF') ? $bytes : null;
        } catch (\Throwable $e) {
            Log::warning('Annual archive: could not render document', ['type' => $type, 'id' => $document->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function pageCount(string $path): int
    {
        return (new Fpdi)->setSourceFile($path);
    }

    /**
     * Cover + table of contents. The page each document starts on depends
     * on how long the cover itself is, so it is rendered until that settles.
     */
    private function cover(string $label, int $year, array $rows, array $skipped): array
    {
        $coverPages = 1;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $page = $coverPages + 1;
            $listed = array_map(function ($row) use (&$page) {
                $row['page'] = $page;
                $page += $row['pages'];

                return $row;
            }, $rows);

            $bytes = Pdf::loadView('pdf.annual-archive-cover', [
                'label' => $label,
                'year' => $year,
                'rows' => $listed,
                'skipped' => $skipped,
                'totalPages' => $page - 1,
                'generatedAt' => now(self::TIMEZONE)->locale('id'),
            ])->setPaper('a4')->output();
            $path = tempnam(sys_get_temp_dir(), 'archive-cover-');
            file_put_contents($path, $bytes);
            $pages = $this->pageCount($path);
            if ($pages === $coverPages) {
                return ['path' => $path];
            }
            @unlink($path);
            $coverPages = $pages;
        }

        return ['path' => $path];
    }
}
