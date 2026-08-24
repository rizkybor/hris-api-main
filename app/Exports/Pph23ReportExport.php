<?php

namespace App\Exports;

use App\Models\PaymentReceipt;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Pph23ReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return PaymentReceipt::query()
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->where('pph23_amount', '>', 0)
            ->with('invoice:id,invoice_number,client_name')
            ->orderBy('date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Nomor Receipt', 'Nomor Invoice', 'Nama Klien', '% PPh 23', 'Gross Amount', 'PPh 23 Dipotong', 'Net Diterima'];
    }

    public function map($receipt): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            optional($receipt->date)->format('d F Y'),
            $receipt->receipt_number,
            $receipt->invoice?->invoice_number ?? '-',
            $receipt->invoice?->client_name ?? $receipt->received_from,
            (float) $receipt->pph23_percent,
            (float) $receipt->amount + (float) $receipt->pph23_amount,
            (float) $receipt->pph23_amount,
            (float) $receipt->amount,
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
        return 'PPh 23 Report';
    }
}
