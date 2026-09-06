<?php

namespace App\Jobs;

use App\Interfaces\PayrollRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeneratePayrollJob implements ShouldQueue
{
    use Queueable;

    public string $salaryMonth;

    public bool $regenerate;

    /**
     * Create a new job instance.
     */
    public function __construct(string $salaryMonth, bool $regenerate = false)
    {
        $this->salaryMonth = $salaryMonth;
        $this->regenerate = $regenerate;
    }

    /**
     * Execute the job.
     */
    public function handle(PayrollRepositoryInterface $payrollRepository): void
    {
        try {
            Log::info('Starting payroll generation', [
                'salary_month' => $this->salaryMonth,
                'regenerate' => $this->regenerate,
            ]);

            $payroll = $payrollRepository->generatePayroll($this->salaryMonth, $this->regenerate);

            Log::info('Payroll generation completed', [
                'salary_month' => $this->salaryMonth,
                'payroll_id' => $payroll->id,
                'total_details' => $payroll->payrollDetails()->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Payroll generation failed', [
                'salary_month' => $this->salaryMonth,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes for large datasets
}
