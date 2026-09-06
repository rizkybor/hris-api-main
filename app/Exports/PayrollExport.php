<?php

namespace App\Exports;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $payrollId;

    protected $payroll;

    protected $rowNumber = 0;

    protected $totalWorkingDays;

    public function __construct($payrollId)
    {
        $this->payrollId = $payrollId;
        $this->payroll = Payroll::findOrFail($payrollId);

        $monthStart = Carbon::parse($this->payroll->salary_month)->startOfMonth();
        $this->totalWorkingDays = $this->calculateWorkingDays($monthStart, $monthStart->copy()->endOfMonth());
    }

    /**
     * Count weekdays (Mon-Fri) between two dates, inclusive.
     */
    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            if (! $currentDate->isWeekend()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }

        return $workingDays;
    }

    /**
     * Get the collection of payroll details
     */
    public function collection()
    {
        return PayrollDetail::where('payroll_id', $this->payrollId)
            ->with([
                'employee.user',
                'employee.jobInformation.team',
                'employee.bankInformation',
                'sourceProject',
            ])
            ->orderBy('final_salary', 'desc')
            ->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'No',
            'Nama Karyawan',
            'ID Karyawan',
            'Jabatan',
            'Departemen',
            'Bank',
            'No. Rekening',
            'Nama Pemegang Rekening',
            'Hari Kerja',
            'Hadir',
            'Sakit',
            'Alpha',
            'Gaji Pokok',
            'Potongan Absensi',
            'BPJS Kesehatan',
            'BPJS JHT',
            'BPJS JP',
            'PPh 21',
            'Gaji Bersih',
            'Skema Pembayaran',
            'Status',
            'Catatan',
        ];
    }

    /**
     * Map each row to the desired format
     */
    public function map($detail): array
    {
        $this->rowNumber++;
        $isProjectPercentage = $detail->payment_mode === 'project_percentage';

        return [
            $this->rowNumber,
            $detail->employee->user->name ?? 'N/A',
            $detail->employee->code ?? $detail->employee->id,
            $detail->employee->jobInformation->job_title ?? 'N/A',
            $detail->employee->jobInformation->team->name ?? 'N/A',
            $detail->employee->bankInformation->bank_name ?? 'N/A',
            $detail->employee->bankInformation->account_number ?? 'N/A',
            $detail->employee->bankInformation->account_holder_name ?? 'N/A',
            $this->totalWorkingDays,
            $detail->attended_days ?? 0,
            $detail->sick_days ?? 0,
            $detail->absent_days ?? 0,
            $detail->original_salary ?? 0,
            // Attendance-based deduction doesn't apply to a percentage-of-project
            // payout -- original_salary is left over from the automatic
            // generation baseline and isn't comparable to that scheme.
            $isProjectPercentage ? 'N/A' : ($detail->original_salary - $detail->gross_salary) ?? 0,
            $detail->bpjs_kesehatan_employee ?? 0,
            $detail->bpjs_jht_employee ?? 0,
            $detail->bpjs_jp_employee ?? 0,
            $detail->pph21 ?? 0,
            $detail->final_salary ?? 0,
            $isProjectPercentage
                ? "{$detail->project_percentage}% dari Project ".($detail->sourceProject->name ?? '-')
                : 'Manual/Otomatis',
            $this->payroll->status === 'paid' ? 'Sudah Dibayar' : 'Menunggu',
            $detail->notes ?? '',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0C51D9'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Define the sheet title
     */
    public function title(): string
    {
        $month = \Carbon\Carbon::parse($this->payroll->salary_month)->format('F Y');

        return "Payroll {$month}";
    }

    /**
     * Register events for additional styling
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Add borders to all cells
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Center align specific columns
                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
                $sheet->getStyle("I2:L{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Attendance columns
                $sheet->getStyle("U2:U{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Status

                // Right align currency columns
                $sheet->getStyle("M2:S{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Salary/BPJS/PPh21 columns

                // Format currency columns
                $sheet->getStyle("M2:S{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Set row height for header
                $sheet->getRowDimension(1)->setRowHeight(25);

                // Add summary information at the top (before the table)
                $sheet->insertNewRowBefore(1, 5);

                $month = \Carbon\Carbon::parse($this->payroll->salary_month)->format('F Y');
                $totalEmployees = $this->payroll->payrollDetails()->count();
                $totalAmount = $this->payroll->payrollDetails()->sum('final_salary');
                $avgSalary = $totalEmployees > 0 ? $totalAmount / $totalEmployees : 0;

                $sheet->setCellValue('A1', 'LAPORAN GAJI KARYAWAN');
                $sheet->setCellValue('A2', "Periode: {$month}");
                $sheet->setCellValue('A3', "Total Karyawan: {$totalEmployees}");
                $sheet->setCellValue('A3', "Total Karyawan: {$totalEmployees}");
                $sheet->setCellValue('D3', 'Total Gaji: Rp '.number_format($totalAmount, 0, ',', '.'));
                $sheet->setCellValue('A4', 'Rata-rata Gaji: Rp '.number_format($avgSalary, 0, ',', '.'));
                $sheet->setCellValue('D4', 'Status: '.($this->payroll->status === 'paid' ? 'Sudah Dibayar' : 'Menunggu'));

                if ($this->payroll->payment_date) {
                    $paymentDate = \Carbon\Carbon::parse($this->payroll->payment_date)->format('d F Y');
                    $sheet->setCellValue('A5', "Tanggal Pembayaran: {$paymentDate}");
                }

                // Style the summary section
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->mergeCells('A1:V1');

                $sheet->getStyle('A2:V5')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                    ],
                ]);
            },
        ];
    }
}
