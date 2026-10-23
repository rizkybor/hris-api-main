<?php

namespace App\Exports;

use App\Interfaces\ReportRepositoryInterface;
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
 * Finance report, Operational Cost source: every Fixed Cost, SDM Resource and
 * Infrastructure item with its status. Totals count only items in use, the
 * same as the Operational Cost page -- standing costs, so not filtered by date.
 */
class FinanceReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    private int $dataRows = 0;

    public function array(): array
    {
        $report = app(ReportRepositoryInterface::class)->getFinanceReport(null, null, 'operational_cost');

        $out = [];
        foreach ($report['rows'] as $i => $row) {
            $out[] = [
                $i + 1,
                $row['category'],
                $row['name'],
                $row['monthly'],
                $row['annual'],
                $row['in_use'] ? 'Digunakan' : 'Tidak Digunakan',
                $row['expired_date'] ?? '',
                $row['notes'] ?? '',
            ];
        }
        $this->dataRows = count($out);

        $s = $report['summary'];
        $out[] = [''];
        $out[] = ['', 'TOTAL (hanya item Digunakan)', 'Fixed Cost', $s['fixed_cost'], null, '', '', ''];
        $out[] = ['', '', 'SDM Resource', $s['sdm'], null, '', '', ''];
        $out[] = ['', '', 'Infrastructure', $s['infrastructure_monthly'], $s['infrastructure_annual'], '', '', ''];

        return $out;
    }

    public function headings(): array
    {
        return ['No', 'Kategori', 'Nama Item', 'Biaya / Bulan', 'Biaya / Tahun (Infra)', 'Status', 'Expired', 'Catatan'];
    }

    public function columnFormats(): array
    {
        return ['D' => '#,##0', 'E' => '#,##0'];
    }

    public function styles(Worksheet $sheet)
    {
        $totalRow = $this->dataRows + 3;

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C51D9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $totalRow => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Operational Cost';
    }
}
