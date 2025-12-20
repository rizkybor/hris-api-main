<?php

namespace App\Interfaces;

interface TeamRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $leaderId,
        ?string $status,
        ?string $department,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        ?int $leaderId,
        ?string $status,
        ?string $department,
        ?int $rowPerPage
    );

    public function getById(
        string $id
    );

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

    public function getStatistics();

    public function getTeamStatistics(string $teamId);

    public function getTeamChartData(string $teamId);

    public function addMember(string $teamId, int $employeeId);

    public function removeMember(string $teamId, int $employeeId);
}
