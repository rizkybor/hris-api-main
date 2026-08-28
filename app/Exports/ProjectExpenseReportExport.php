<?php

namespace App\Exports;

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
        protected ?int $projectId = null,
    ) {
        $this->startDate = $startDate ?: now()->startOfYear()->toDateString();
        $this->endDate = $endDate ?: now()->endOfYear()->toDateString();
    }

    public function collection()
    {
        $query = ProjectCashTransaction::query()
            ->with('project:id,name')
            ->whereBetween('transaction_date', [$this->startDate, $this->endDate]);

        if ($this->projectId) {
            $query->where('project_id', $this->projectId);
        }

        return $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->get();
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Nama Project', 'Keterangan', 'Debit', 'Kredit'];
    }

    public function map($transaction): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        $amount = (float) $transaction->amount;

        return [
            $rowNumber,
            optional($transaction->transaction_date)->format('d M Y'),
            $transaction->project?->name ?? 'N/A',
            $transaction->description,
            // Debit = money in, Credit = money out (Indonesian "buku kas"
            // convention -- see ProjectCashTransactionController).
            $transaction->type === 'debit' ? $amount : 0,
            $transaction->type === 'credit' ? $amount : 0,
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
