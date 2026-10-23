<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\DocumentLetter;
use App\Models\Invoice;
use App\Models\Letter;
use App\Models\MeetingNote;
use App\Models\PaymentReceipt;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Recap of everything under Business Documents for a date range: counts per
 * document type and status, documents per month, invoiced vs received, the
 * proposal pipeline. Each type is dated by its own document date (meeting
 * date for meeting notes, issue date for certificates).
 */
class DocumentReportService
{
    private const TZ = 'Asia/Jakarta';

    /** key => [label, model, date column, status column|null, statuses => labels] */
    private function types(): array
    {
        return [
            'proposal' => ['Proposal', Proposal::class, 'proposal_date', 'status', ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected']],
            'invoice' => ['Invoice', Invoice::class, 'date', 'status', ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'cancelled' => 'Cancelled']],
            'payment_receipt' => ['Payment Receipt', PaymentReceipt::class, 'date', 'payment_status', ['paid' => 'Paid in full', 'partial' => 'Partial']],
            'purchase_order' => ['Purchase Order', PurchaseOrder::class, 'date', 'type', ['I' => 'Internal', 'E' => 'External']],
            'letter' => ['Letter', Letter::class, 'date', 'type', ['I' => 'Internal', 'E' => 'External']],
            'official_memo' => ['Official Memo', DocumentLetter::class, 'document_date', 'status', ['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']],
            'meeting_note' => ['Meeting Note', MeetingNote::class, 'meeting_date', 'meeting_type', ['internal' => 'Internal', 'external' => 'External']],
            'certificate' => ['Certificate', Certificate::class, 'created_at', null, []],
        ];
    }

    public function summary(?string $startDate, ?string $endDate): array
    {
        $startDate = $startDate ?: Carbon::now(self::TZ)->startOfYear()->toDateString();
        $endDate = $endDate ?: Carbon::now(self::TZ)->endOfYear()->toDateString();
        $months = $this->months($startDate, $endDate);

        $types = [];
        $perMonth = array_fill_keys($months, 0);
        $loaded = [];

        foreach ($this->types() as $key => [$label, $model, $dateCol, $statusCol, $statusLabels]) {
            $rows = $model::query()
                ->whereDate($dateCol, '>=', $startDate)
                ->whereDate($dateCol, '<=', $endDate)
                ->get();
            $loaded[$key] = $rows;

            foreach ($rows as $row) {
                $m = $this->monthOf($row->{$dateCol}, $dateCol === 'created_at' || $dateCol === 'meeting_date');
                if (isset($perMonth[$m])) {
                    $perMonth[$m]++;
                }
            }

            $types[] = [
                'key' => $key,
                'label' => $label,
                'count' => $rows->count(),
                'breakdown' => $statusCol
                    ? collect($statusLabels)->map(fn ($text, $value) => [
                        'value' => $value,
                        'label' => $text,
                        'count' => $rows->filter(fn ($r) => (string) $r->{$statusCol} === (string) $value)->count(),
                    ])->values()->all()
                    : [],
            ];
        }

        $invoices = $loaded['invoice'];
        $receipts = $loaded['payment_receipt']->where('status', '!=', 'cancelled');
        $proposals = $loaded['proposal'];
        $decided = $proposals->whereIn('status', ['accepted', 'rejected'])->count();

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_documents' => array_sum(array_column($types, 'count')),
                'invoice_total' => $this->sum($invoices->where('status', '!=', 'cancelled'), 'total'),
                'invoice_unpaid' => $this->sum($invoices->where('status', 'unpaid'), 'total'),
                'invoice_unpaid_count' => $invoices->where('status', 'unpaid')->count(),
                'received_total' => $this->sum($receipts, 'amount'),
                'receipt_count' => $receipts->count(),
                'proposal_win_rate' => $decided ? round($proposals->where('status', 'accepted')->count() / $decided * 100, 1) : null,
                'proposal_decided' => $decided,
            ],
            'types' => $types,
            'per_month' => collect($perMonth)->map(fn ($count, $month) => ['month' => $month, 'count' => $count])->values()->all(),
            'money_per_month' => $this->moneyPerMonth($months, $invoices->where('status', '!=', 'cancelled'), $receipts),
            'invoice_status' => collect(['unpaid', 'paid', 'cancelled'])->map(fn ($s) => [
                'status' => $s,
                'count' => $invoices->where('status', $s)->count(),
                'amount' => $this->sum($invoices->where('status', $s), 'total'),
            ])->values()->all(),
        ];
    }

    private function moneyPerMonth(array $months, Collection $invoices, Collection $receipts): array
    {
        $bucket = fn (Collection $rows, string $col) => $rows->groupBy(fn ($r) => $this->monthOf($r->date))
            ->map(fn ($g) => $this->sum($g, $col));
        $invoiced = $bucket($invoices, 'total');
        $received = $bucket($receipts, 'amount');

        return array_map(fn ($m) => [
            'month' => $m,
            'invoiced' => (float) ($invoiced[$m] ?? 0),
            'received' => (float) ($received[$m] ?? 0),
        ], $months);
    }

    private function sum(Collection $rows, string $col): float
    {
        return round((float) $rows->sum(fn ($r) => (float) $r->{$col}), 2);
    }

    /** Date-only columns are calendar dates already; timestamps are UTC -> WIB. */
    private function monthOf($value, bool $timestamp = false): string
    {
        $date = Carbon::parse($value);

        return ($timestamp ? $date->setTimezone(self::TZ) : $date)->format('Y-m');
    }

    /** "Y-m" for every month in the range, capped at 24. */
    private function months(string $start, string $end): array
    {
        $from = Carbon::parse($start)->startOfMonth();
        $to = Carbon::parse($end)->startOfMonth();
        $out = [];
        while ($from->lte($to) && count($out) < 24) {
            $out[] = $from->format('Y-m');
            $from->addMonthNoOverflow();
        }

        return $out;
    }
}
