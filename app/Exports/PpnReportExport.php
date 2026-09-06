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
        return [
            'No', 'Tanggal', 'Nomor Faktur Pajak', 'Nomor Invoice', 'Nama Klien', 'NPWP Klien',
            'DPP', '% PPN', 'PPN',
            // Reference only -- not part of DPP/PPN (the actual tax filing
            // figures above), same as they're excluded from the invoice's
            // own subtotal/ppn_amount.
            'Admin Fee', 'ICANN Fee', 'Total Invoice',
        ];
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
            self::numberCell($invoice->subtotal),
            self::numberCell($invoice->ppn_percentage),
            self::numberCell($invoice->ppn_amount),
            self::numberCell($invoice->admin_fee),
            self::numberCell($invoice->icann_fee),
            self::numberCell($invoice->total),
        ];
    }

    /**
     * Maatwebsite Excel/PhpSpreadsheet silently drops a literal int/float 0
     * to a blank cell instead of writing 0 -- cast to string to keep a
     * legitimate zero (e.g. no Admin Fee/ICANN Fee on this invoice) visible.
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

    public function title(): string
    {
        return 'PPN Report';
    }
}
