<?php

namespace App\Interfaces;

interface AttendanceRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $date,
        ?int $limit,
        bool $execute,
        ?string $startDate = null,
        ?string $endDate = null
    );

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage,
        ?string $status = null,
        ?string $startDate = null,
        ?string $endDate = null
    );

    public function getMyAttendances();

    public function getMyAttendanceStatistics();

    public function getById(string $id);

    public function getLastAttendanceByEmployee();

    public function checkIn(array $data);

    public function checkOut(array $data);

    public function getStatistics();
}
