<?php

namespace App\Interfaces;

interface ReportRepositoryInterface
{
    public function getAttendanceReport(?string $startDate, ?string $endDate, ?int $employeeId, ?string $status, int $page = 1, int $rowPerPage = 15);
    public function getPayrollReport(?string $startDate, ?string $endDate, int $page = 1, int $rowPerPage = 15);
    public function getEmployeeReport(?int $teamId, ?string $status);
    public function getFinanceReport(?string $startDate, ?string $endDate);

    public function getPph21Report(?string $startDate, ?string $endDate);

    public function getPpnReport(?string $startDate, ?string $endDate);
}
