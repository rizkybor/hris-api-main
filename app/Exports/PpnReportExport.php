<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PpnReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return Invoice::query()
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('ppn_amount', '>', 0)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Nomor Faktur Pajak', 'Nomor Invoice', 'Nama Klien', 'NPWP Klien', 'DPP', '% PPN', 'PPN'];
    }

    public function map($invoice): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            optional($invoice->date)->format('d F Y'),
            $invoice->faktur_pajak_number ?? '-',
            $invoice->invoice_number,
            $invoice->client_name,
            $invoice->client_npwp ?? '-',
            (float) $invoice->subtotal,
            (float) $invoice->ppn_percentage,
            (float) $invoice->ppn_amount,
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
        return 'PPN Report';
    }
}
