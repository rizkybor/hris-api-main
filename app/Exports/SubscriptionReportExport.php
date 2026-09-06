<?php

namespace App\Exports;

use App\Models\Subscription;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubscriptionReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    // Subscription-level columns repeat on every one of a subscription's
    // service rows -- merged (registerEvents()) across each group's row
    // range so the sheet reads as "1 subscription = 1 grouped block" with
    // only the item-specific columns (Service Type/Product Name/Service
    // Amount) varying per row.
    private const GROUPED_COLUMNS = ['B', 'C', 'D', 'J', 'K', 'L', 'M', 'N', 'O'];

    private const LAST_COLUMN = 'O';

    public function __construct(
        protected ?string $status = null,
    ) {}

    /**
     * @var array<int, array{start:int,end:int}> spreadsheet row ranges (1
     * header row + 1-indexed data rows) for each subscription's group,
     * built while the rows are produced so registerEvents() can merge them.
     */
    private array $groupRanges = [];

    /**
     * One row per bundled service (not per subscription) so the exported
     * sheet shows the same item-level detail as the Subscription Detail
     * modal -- a subscription's own columns are repeated on every one of
     * its service rows, then visually merged back together.
     */
    public function collection()
    {
        $subscriptions = Subscription::query()
            ->with(['client:id,name', 'project:id,name', 'services'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('next_due_date')
            ->get();

        $rows = collect();
        $currentRow = 2; // row 1 is the header

        foreach ($subscriptions as $subscription) {
            $services = $subscription->services->isNotEmpty() ? $subscription->services : collect([null]);

            $this->groupRanges[] = ['start' => $currentRow, 'end' => $currentRow + $services->count() - 1];

            foreach ($services as $service) {
                $rows->push((object) ['subscription' => $subscription, 'service' => $service]);
                $currentRow++;
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'No', 'Name', 'Client', 'Project',
            'Service Type', 'Product Name', 'Service Amount', 'Service VAT/PPN %', 'Service ICANN Fee',
            'Billing Cycle', 'Total Amount', 'Total VAT/PPN %', 'Status', 'Next Due Date', 'Last Invoiced',
        ];
    }

    public function map($row): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        $subscription = $row->subscription;
        $service = $row->service;

        return [
            $rowNumber,
            $subscription->name,
            $subscription->client?->name ?? '-',
            $subscription->project?->name ?? '-',
            $service ? str_replace('_', ' ', $service->service_type) : '-',
            // Product Name only ever gets filled in for a saas_subscription
            // service -- other service types (maintenance, domain renewal)
            // recur as-is each period rather than naming a product.
            $service
                ? ($service->product_name ?: ($service->service_type === 'saas_subscription' ? '-' : 'Renewable'))
                : '-',
            $service ? self::numberCell($service->amount) : self::numberCell(0),
            self::numberCell($service?->ppn_percentage ?? 0),
            self::numberCell($service?->icann_fee ?? 0),
            $subscription->billing_cycle,
            self::numberCell($subscription->amount),
            self::numberCell($subscription->ppn_percentage),
            $subscription->status === 'cancelled' ? 'Not Active' : ucfirst($subscription->status),
            optional($subscription->next_due_date)->format('d F Y'),
            optional($subscription->last_invoiced_at)->format('d F Y') ?? '-',
        ];
    }

    /**
     * Maatwebsite Excel/PhpSpreadsheet silently drops a literal int/float 0
     * to a blank cell (verified: a plain 0.0 in the row array never makes
     * it into the written file, while the string "0" does) -- a service
     * legitimately has 0% PPN whenever it's left unset, so numeric columns
     * that can land on exactly zero are cast to string to avoid losing it.
     */
    private static function numberCell(mixed $value): string
    {
        return (string) (float) $value;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C51D9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->groupRanges as $range) {
                    // Merge the subscription-level columns across this
                    // group's rows so a multi-service subscription reads
                    // as one block instead of repeating identical values.
                    if ($range['end'] > $range['start']) {
                        foreach (self::GROUPED_COLUMNS as $col) {
                            $sheet->mergeCells("{$col}{$range['start']}:{$col}{$range['end']}");
                            $sheet->getStyle("{$col}{$range['start']}:{$col}{$range['end']}")
                                ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                        }
                    }

                    // A thin bottom border under the group's last row
                    // marks where one subscription's block ends and the
                    // next begins.
                    $sheet->getStyle('A'.$range['end'].':'.self::LAST_COLUMN.$range['end'])
                        ->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_THIN);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Subscription Report';
    }
}
