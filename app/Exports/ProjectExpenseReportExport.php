<?php

namespace App\Exports;

use App\Models\Project;
use App\Models\ProjectCashTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectExpenseReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
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
        $projectIds = ProjectCashTransaction::whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->distinct()
            ->pluck('project_id');

        return Project::query()
            ->whereIn('id', $projectIds)
            ->with('projectLeader.user')
            ->withSum(['cashTransactions as debit_sum' => function ($q) {
                $q->where('type', 'debit')->whereBetween('transaction_date', [$this->startDate, $this->endDate]);
            }], 'amount')
            ->withSum(['cashTransactions as credit_sum' => function ($q) {
                $q->where('type', 'credit')->whereBetween('transaction_date', [$this->startDate, $this->endDate]);
            }], 'amount')
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return ['No', 'Nama Project', 'Project Leader', 'Budget (Saldo Awal)', 'Total Debit', 'Total Kredit', 'Saldo Akhir'];
    }

    public function map($project): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        $budget = (float) ($project->budget ?? 0);
        $debit = (float) ($project->debit_sum ?? 0);
        $credit = (float) ($project->credit_sum ?? 0);

        return [
            $rowNumber,
            $project->name,
            $project->projectLeader?->user?->name ?? 'N/A',
            $budget,
            $debit,
            $credit,
            // Debit = money in, Credit = money out (Indonesian "buku kas"
            // convention -- see ProjectCashTransactionController).
            $budget + $debit - $credit,
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
        return 'Project Cash Ledger';
    }
}
