<?php

namespace App\Exports;

use App\Models\Project;
use App\Services\ProjectBillingService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected ?string $startDate = null,
        protected ?string $endDate = null,
        protected ?string $status = null,
    ) {
        $this->startDate = $startDate ?: now('Asia/Jakarta')->startOfYear()->toDateString();
        $this->endDate = $endDate ?: now('Asia/Jakarta')->endOfYear()->toDateString();
    }

    public function collection()
    {
        $query = Project::query()
            ->with(['projectLeader.user', 'client'])
            ->withCount([
                'tasks as tasks_total_count',
                'tasks as tasks_done_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->whereBetween('start_date', [$this->startDate, $this->endDate]);

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $projects = $query->orderBy('start_date', 'desc')->get();

        // Same invoiced/paid figures as the report tab and project detail.
        $billing = app(ProjectBillingService::class);
        $invoices = $billing->invoicesFor($projects->pluck('id')->all());
        $projects->each(fn ($p) => $p->setAttribute('billing', $billing->summary((float) $p->budget, $invoices->get($p->id, collect()))));

        return $projects;
    }

    private const BILLING_STATUS = [
        'settled' => 'Settled',
        'outstanding' => 'Outstanding',
        'overpaid' => 'Overpaid',
        'not_invoiced' => 'Not invoiced',
    ];

    public function headings(): array
    {
        return ['No', 'Nama Project', 'Project Leader', 'Client', 'Status', 'Prioritas', 'Tanggal Mulai', 'Tanggal Selesai', 'Budget', 'Task Selesai', 'Invoiced', 'Paid', 'Outstanding', 'Not Invoiced (DPP)', 'Billing Status'];
    }

    public function map($project): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $project->name,
            $project->projectLeader?->user?->name ?? 'N/A',
            $project->client?->name ?? '-',
            ucfirst(str_replace('_', ' ', $project->status)),
            ucfirst($project->priority),
            optional($project->start_date)->format('d M Y'),
            optional($project->end_date)->format('d M Y'),
            (float) ($project->budget ?? 0),
            "{$project->tasks_done_count}/{$project->tasks_total_count}",
            (float) $project->billing['invoiced_total'],
            (float) $project->billing['paid_total'],
            (float) $project->billing['outstanding'],
            (float) $project->billing['not_invoiced'],
            self::BILLING_STATUS[$project->billing['status']] ?? $project->billing['status'],
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
        return 'Project Report';
    }
}
