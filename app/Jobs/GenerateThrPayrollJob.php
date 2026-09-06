<?php

namespace App\Jobs;

use App\Interfaces\PayrollRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateThrPayrollJob implements ShouldQueue
{
    use Queueable;

    public string $salaryMonth;

    public bool $regenerate;

    public function __construct(string $salaryMonth, bool $regenerate = false)
    {
        $this->salaryMonth = $salaryMonth;
        $this->regenerate = $regenerate;
    }

    public function handle(PayrollRepositoryInterface $payrollRepository): void
    {
        try {
            Log::info('Starting THR payroll generation', [
                'salary_month' => $this->salaryMonth,
                'regenerate' => $this->regenerate,
            ]);

            $payroll = $payrollRepository->generateThrPayroll($this->salaryMonth, $this->regenerate);

            Log::info('THR payroll generation completed', [
                'salary_month' => $this->salaryMonth,
                'payroll_id' => $payroll->id,
                'total_details' => $payroll->payrollDetails()->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('THR payroll generation failed', [
                'salary_month' => $this->salaryMonth,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public int $tries = 3;

    public int $timeout = 600;
}
