<?php

namespace App\Exports;

use App\Models\EmployeeProfile;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected ?int $teamId = null,
        protected ?string $status = null,
    ) {}

    public function collection()
    {
        $query = EmployeeProfile::query()->with(['user', 'jobInformation.team']);

        if ($this->teamId) {
            $query->whereHas('jobInformation', fn ($q) => $q->where('team_id', $this->teamId));
        }

        if ($this->status) {
            $query->whereHas('jobInformation', fn ($q) => $q->where('status', $this->status));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return ['No', 'Kode', 'Nama', 'Email', 'Jabatan', 'Departemen', 'Status', 'Telepon'];
    }

    public function map($employee): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $employee->code ?? $employee->id,
            $employee->user->name ?? 'N/A',
            $employee->user->email ?? 'N/A',
            $employee->jobInformation->job_title ?? 'N/A',
            $employee->jobInformation->team->name ?? 'N/A',
            ucfirst($employee->jobInformation->status ?? 'N/A'),
            $employee->phone ?? '-',
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
        return 'Employee Report';
    }
}
