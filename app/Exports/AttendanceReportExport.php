<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        protected ?string $startDate = null,
        protected ?string $endDate = null,
        protected ?int $employeeId = null,
        protected ?string $status = null,
    ) {
        $this->startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $this->endDate = $endDate ?: now()->endOfMonth()->toDateString();
    }

    public function collection()
    {
        $query = Attendance::query()
            ->with(['employee.user', 'employee.jobInformation.team'])
            ->whereBetween('date', [$this->startDate, $this->endDate]);

        if ($this->employeeId) {
            $query->where('employee_id', $this->employeeId);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->orderBy('date', 'desc')->get();
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Nama Karyawan', 'Departemen', 'Jam Masuk', 'Jam Keluar', 'Status', 'Catatan'];
    }

    public function map($attendance): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            optional($attendance->date)->format('d-m-Y'),
            $attendance->employee->user->name ?? 'N/A',
            $attendance->employee->jobInformation->team->name ?? 'N/A',
            $attendance->check_in ? $attendance->check_in->format('H:i') : '-',
            $attendance->check_out ? $attendance->check_out->format('H:i') : '-',
            ucfirst(str_replace('_', ' ', $attendance->status)),
            $attendance->notes ?? '',
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
        return 'Attendance Report';
    }
}
