<?php

namespace App\Exports;

use App\Interfaces\ReportRepositoryInterface;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Maintenance Lists report: one row per contract with its quota use in the
 * chosen month and its development balance -- same data as the report tab.
 */
class MaintenanceReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    private const STATUS = ['active' => 'Aktif', 'paused' => 'Dijeda', 'ended' => 'Selesai'];

    private const LEFTOVER = ['expire' => 'Hangus', 'rollover' => 'Akumulasi', 'bank' => 'Saldo Pengembangan'];

    public function __construct(
        protected ?string $status = null,
        protected ?string $monthParam = null,
    ) {}

    public function array(): array
    {
        $report = app(ReportRepositoryInterface::class)->getMaintenanceReport($this->status, $this->monthParam);

        return collect($report['rows'])->values()->map(function ($r, $i) {
            $p = $r['period'];

            return [
                $i + 1,
                $r['name'],
                $r['client'] ?? '-',
                $r['project'] ?? '-',
                self::STATUS[$r['status']] ?? $r['status'],
                $r['monthly_quota_hours'],
                $p['total'] ?? null,
                $p['used'] ?? null,
                $p['remaining'] ?? null,
                $p['overage'] ?? null,
                $p ? $p['used_percent'] / 100 : null,
                self::LEFTOVER[$r['leftover_policy']] ?? $r['leftover_policy'],
                $r['balance'],
                $r['expiring'],
                $r['start_date'] ? Carbon::parse($r['start_date'])->format('d/m/Y') : '',
                $r['end_date'] ? Carbon::parse($r['end_date'])->format('d/m/Y') : '-',
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'No', 'Kontrak', 'Klien', 'Project', 'Status', 'Kuota / Bulan (jam)',
            'Tersedia (jam)', 'Terpakai (jam)', 'Sisa (jam)', 'Lebih Kuota (jam)', 'Pemakaian',
            'Sisa Kuota', 'Saldo Pengembangan (jam)', 'Hangus ≤30 hari (jam)', 'Mulai', 'Berakhir',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('K:K')->getNumberFormat()->setFormatCode('0%');

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
        return 'Maintenance Lists';
    }
}
