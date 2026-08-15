<?php

namespace App\Interfaces;

interface ReportRepositoryInterface
{
    public function getAttendanceReport(?string $startDate, ?string $endDate, ?int $employeeId, ?string $status);
    public function getPayrollReport(?string $startDate, ?string $endDate);
    public function getEmployeeReport(?int $teamId, ?string $status);
    public function getFinanceReport(?string $startDate, ?string $endDate);
}
