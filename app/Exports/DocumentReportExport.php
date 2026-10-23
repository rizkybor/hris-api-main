<?php

namespace App\Exports;

use App\Services\DocumentReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Document Reports as a sheet: totals, documents per type with their status
 * breakdown, documents per month, and invoiced vs received per month -- the
 * same figures as the report tab's charts.
 */
class DocumentReportExport implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    /** Row numbers of section headings, bolded in styles(). */
    private array $headingRows = [];

    public function __construct(protected ?string $startDate = null, protected ?string $endDate = null) {}

    public function array(): array
    {
        $r = app(DocumentReportService::class)->summary($this->startDate, $this->endDate);
        $s = $r['summary'];
        $out = [];
        $heading = function (array $row) use (&$out) {
            $out[] = $row;
            $this->headingRows[] = count($out);
        };

        $heading(['Document Reports', $r['period']['start_date'].' s/d '.$r['period']['end_date']]);
        $out[] = ['Total dokumen', $s['total_documents']];
        $out[] = ['Nilai invoice (tidak termasuk cancelled)', $s['invoice_total']];
        $out[] = ['Invoice belum dibayar', $s['invoice_unpaid'], $s['invoice_unpaid_count'].' invoice'];
        $out[] = ['Pembayaran diterima', $s['received_total'], $s['receipt_count'].' receipt'];
        $out[] = ['Win rate proposal', $s['proposal_win_rate'] !== null ? $s['proposal_win_rate'].'%' : '-', $s['proposal_decided'].' proposal diputuskan'];
        $out[] = [''];

        $heading(['Jenis Dokumen', 'Jumlah', 'Rincian']);
        foreach ($r['types'] as $t) {
            $out[] = [$t['label'], $t['count'], collect($t['breakdown'])->map(fn ($b) => "{$b['label']}: {$b['count']}")->implode(' · ')];
        }
        $out[] = [''];

        $heading(['Bulan', 'Jumlah Dokumen', 'Invoice Ditagihkan', 'Pembayaran Diterima']);
        foreach ($r['per_month'] as $i => $m) {
            $money = $r['money_per_month'][$i];
            $out[] = [$m['month'], $m['count'], $money['invoiced'], $money['received']];
        }

        return $out;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B:D')->getNumberFormat()->setFormatCode('#,##0');

        return collect($this->headingRows)->mapWithKeys(fn ($row) => [$row => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C51D9']],
        ]])->all();
    }

    public function title(): string
    {
        return 'Document Reports';
    }
}
