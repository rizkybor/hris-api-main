<?php

namespace App\Exports;

use App\Services\CompanyCashBookService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Finance report, Company Cash Book source: every transaction in the period,
 * oldest first, with the company-wide running balance -- the same figures
 * as the Company Cash Book page and the report tab.
 */
class CompanyCashBookReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    private int $dataRows = 0;

    public function __construct(
        protected ?string $startDate = null,
        protected ?string $endDate = null,
    ) {
        $this->startDate = $startDate ?: now('Asia/Jakarta')->startOfYear()->toDateString();
        $this->endDate = $endDate ?: now('Asia/Jakarta')->endOfYear()->toDateString();
    }

    public function array(): array
    {
        $ledger = app(CompanyCashBookService::class)->ledger(['start_date' => $this->startDate, 'end_date' => $this->endDate]);
        $rows = $ledger['rows'];

        $out = [['', Carbon::parse($this->startDate)->format('d/m/Y'), 'Saldo awal periode', '', '', null, null, $ledger['period_opening']]];
        foreach ($rows->values() as $i => $t) {
            $out[] = [
                $i + 1,
                $t->transaction_date->format('d/m/Y'),
                $t->description,
                $t->notes ?? '',
                $t->isSynced() ? ($t->project?->name ?? 'Project') : 'Manual',
                $t->type === 'debit' ? (float) $t->amount : null,
                $t->type === 'credit' ? (float) $t->amount : null,
                (float) $t->running_balance,
            ];
        }
        $this->dataRows = count($out);

        $debit = (float) $rows->where('type', 'debit')->sum(fn ($t) => (float) $t->amount);
        $credit = (float) $rows->where('type', 'credit')->sum(fn ($t) => (float) $t->amount);
        $out[] = ['', '', 'TOTAL', '', '', $debit, $credit, null];
        $out[] = ['', Carbon::parse($this->endDate)->format('d/m/Y'), 'Saldo akhir periode', '', '', null, null, $ledger['period_closing']];

        return $out;
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Keterangan', 'Catatan', 'Sumber', 'Debit (Masuk)', 'Kredit (Keluar)', 'Saldo'];
    }

    public function columnFormats(): array
    {
        return ['F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0'];
    }

    public function styles(Worksheet $sheet)
    {
        $first = 2;
        $totalRow = $this->dataRows + 2;

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C51D9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $first => ['font' => ['italic' => true]],
            $totalRow => ['font' => ['bold' => true]],
            $totalRow + 1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Company Cash Book';
    }
}
