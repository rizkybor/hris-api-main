<?php

namespace App\Interfaces;

interface LeaveRequestRepositoryInterface
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

    public function getById(
        string $id
    );

    public function getMyLeaveRequests();

    public function store(
        array $data
    );

    public function approve(
        string $id
    );

    public function reject(
        string $id
    );
}
