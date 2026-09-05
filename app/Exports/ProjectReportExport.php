<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected ?string $startDate = null,
        protected ?string $endDate = null,
        protected ?string $status = null,
    ) {
        $this->startDate = $startDate ?: now()->startOfYear()->toDateString();
        $this->endDate = $endDate ?: now()->endOfYear()->toDateString();
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

        return $query->orderBy('start_date', 'desc')->get();
    }

    public function headings(): array
    {
        return ['No', 'Nama Project', 'Project Leader', 'Client', 'Status', 'Prioritas', 'Tanggal Mulai', 'Tanggal Selesai', 'Budget', 'Task Selesai'];
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
