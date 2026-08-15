<?php

namespace App\Exports;

use App\Models\Payroll;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
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
        $payrolls = Payroll::query()
            ->with(['payrollDetails.employee.user', 'payrollDetails.employee.jobInformation.team'])
            ->whereBetween('salary_month', [$this->startDate, $this->endDate])
            ->orderBy('salary_month', 'desc')
            ->get();

        return $payrolls->flatMap(function ($payroll) {
            return $payroll->payrollDetails->map(function ($detail) use ($payroll) {
                $detail->setAttribute('salary_month', $payroll->salary_month);
                $detail->setAttribute('payment_status', $payroll->status);

                return $detail;
            });
        })->values();
    }

    public function headings(): array
    {
        return ['No', 'Periode', 'Nama Karyawan', 'Departemen', 'Gaji Pokok', 'Gaji Bersih', 'Hadir', 'Sakit', 'Alpha', 'Status'];
    }

    public function map($detail): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            optional($detail->salary_month)->format('F Y'),
            $detail->employee->user->name ?? 'N/A',
            $detail->employee->jobInformation->team->name ?? 'N/A',
            (float) $detail->original_salary,
            (float) $detail->final_salary,
            $detail->attended_days ?? 0,
            $detail->sick_days ?? 0,
            $detail->absent_days ?? 0,
            $detail->payment_status === 'paid' ? 'Sudah Dibayar' : 'Menunggu',
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
        return 'Payroll Report';
    }
}
