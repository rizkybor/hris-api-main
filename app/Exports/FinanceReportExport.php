<?php

namespace App\Exports;

use App\Models\FixedCost;
use App\Models\InfrastructureTool;
use App\Models\SdmResource;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinanceReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected ?string $startDate = null,
        protected ?string $endDate = null,
    ) {
        $this->startDate = $startDate ?: now()->startOfYear()->toDateString();
        $this->endDate = $endDate ?: now()->endOfYear()->toDateString();
    }

    public function collection()
    {
        $range = [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'];

        $fixedCosts = FixedCost::whereBetween('created_at', $range)->get()->map(fn ($item) => [
            'category' => 'Fixed Cost',
            'name' => $item->financial_items,
            'monthly_fee' => null,
            'actual' => (float) $item->actual,
            'notes' => $item->notes,
        ]);

        // Infrastructure never had a "Budget" concept -- its own pair is
        // monthly/annual fee, kept as-is (not part of this Budget removal).
        $infrastructure = InfrastructureTool::whereBetween('created_at', $range)->get()->map(fn ($item) => [
            'category' => 'Infrastructure',
            'name' => $item->tech_stack_component,
            'monthly_fee' => (float) $item->monthly_fee,
            'actual' => (float) $item->annual_fee,
            'notes' => $item->notes,
        ]);

        $sdmResources = SdmResource::whereBetween('created_at', $range)->get()->map(fn ($item) => [
            'category' => 'SDM Resource',
            'name' => $item->sdm_component,
            'monthly_fee' => null,
            'actual' => (float) $item->actual,
            'notes' => $item->notes,
        ]);

        return new Collection([...$fixedCosts, ...$infrastructure, ...$sdmResources]);
    }

    public function headings(): array
    {
        return ['No', 'Kategori', 'Nama Item', 'Monthly Fee (Infra)', 'Actual / Annual Fee', 'Catatan'];
    }

    public function map($row): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $row['category'],
            $row['name'],
            $row['monthly_fee'],
            $row['actual'],
            $row['notes'] ?? '',
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
        return 'Finance Report';
    }
}
