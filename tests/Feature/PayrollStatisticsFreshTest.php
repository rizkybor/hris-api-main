<?php

use App\Models\EmployeeProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Repositories\PayrollRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']));

// The summary on a payroll's detail page must follow edits right away -- it
// used to be cached for an hour, so a corrected salary, a deleted line or
// "mark as paid" kept showing the old totals and status.

function payrollWithLine(int $finalSalary): array
{
    $payroll = Payroll::create(['salary_month' => '2026-10-01', 'type' => 'monthly', 'status' => 'pending']);
    $detail = PayrollDetail::create([
        'payroll_id' => $payroll->id,
        'employee_id' => EmployeeProfile::factory()->create()->id,
        'original_salary' => 10000000,
        'gross_salary' => 10000000,
        'final_salary' => $finalSalary,
        'attended_days' => 22,
        'sick_days' => 0,
        'absent_days' => 0,
        'total_deduction' => 10000000 - $finalSalary,
    ]);

    return [$payroll, $detail];
}

it('shows a corrected salary immediately', function () {
    [$payroll, $detail] = payrollWithLine(9000000);
    $repo = app(PayrollRepository::class);

    expect((float) $repo->getPayrollStatistics($payroll->id)['total_amount'])->toBe(9000000.0);

    $detail->update(['final_salary' => 9500000]);

    expect((float) $repo->getPayrollStatistics($payroll->id)['total_amount'])->toBe(9500000.0);
});

it('shows the paid status and a removed line immediately', function () {
    [$payroll, $detail] = payrollWithLine(9000000);
    $repo = app(PayrollRepository::class);

    expect($repo->getPayrollStatistics($payroll->id)['status'])->toBe('pending');

    $repo->markAsPaid($payroll->id, '2026-10-28');
    $detail->delete();

    $stats = $repo->getPayrollStatistics($payroll->id);
    expect($stats['status'])->toBe('paid')
        ->and((int) $stats['total_employees'])->toBe(0);
});
