<?php

namespace App\Interfaces;

use App\Models\EmployeeProfile;

interface EmployeeProfileRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $workLocation,
        ?string $projectId,
        ?int $limit,
        bool $execute,
        ?string $roles = null
    );

    public function getAllPaginated(
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $workLocation,
        ?string $projectId,
        int $rowPerPage
    );

    public function getById(
        string $id
    );

    public function getMyProfile();

    public function create(
        array $data
    );

    public function update(
        string $id,
        array $data
    );

    public function delete(
        string $id
    );

    public function toggleAccountStatus(string $id): EmployeeProfile;

    public function getStatistics();

    public function getContractAlerts(int $daysAhead = 30): array;

    public function getPerformanceStatistics(string $employeeId);

    public function getMyTeam();

    public function getMyTeamMembers();

    public function getMyTeamProjects();
}
