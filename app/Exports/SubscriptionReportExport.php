<?php

namespace App\Exports;

use App\Models\Subscription;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubscriptionReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected ?string $status = null,
    ) {}

    public function collection()
    {
        return Subscription::query()
            ->with(['client:id,name', 'project:id,name', 'services'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('next_due_date')
            ->get();
    }

    public function headings(): array
    {
        return ['No', 'Name', 'Client', 'Project', 'Services', 'Billing Cycle', 'Amount', 'Status', 'Next Due Date', 'Last Invoiced'];
    }

    public function map($subscription): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $subscription->name,
            $subscription->client?->name ?? '-',
            $subscription->project?->name ?? '-',
            // A subscription can bundle several services -- list them all
            // in one cell rather than a single Service Type column.
            $subscription->services->map(
                fn ($service) => $service->product_name ? "{$service->service_type} ({$service->product_name})" : $service->service_type
            )->implode(', '),
            $subscription->billing_cycle,
            (float) $subscription->amount,
            $subscription->status === 'cancelled' ? 'Not Active' : ucfirst($subscription->status),
            optional($subscription->next_due_date)->format('d F Y'),
            optional($subscription->last_invoiced_at)->format('d F Y') ?? '-',
        ];
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

    public function title(): string
    {
        return 'Subscription Report';
    }
}
