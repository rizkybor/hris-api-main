<?php

namespace App\Interfaces;

interface PayrollRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    );

    public function getById(string $id);

    public function getPayrollPositions(string $payrollId);

    public function getPayrollDetailsPaginated(string $payrollId, int $perPage, ?string $search = null, ?string $position = null);

    public function deletePayroll(string $id);

    public function deletePayrollDetail(string $id);

    public function generatePayroll(string $salaryMonth, bool $regenerate = false);

    public function generateThrPayroll(string $salaryMonth, bool $regenerate = false);

    public function updatePayrollDetail(string $id, array $data);

    public function markAsPaid(string $payrollId, string $paymentDate);

    public function getStatistics();

    public function getPayrollStatistics(string $payrollId);
}
