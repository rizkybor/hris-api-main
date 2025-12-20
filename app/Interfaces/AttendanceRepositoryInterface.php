<?php

namespace App\Interfaces;

interface AttendanceRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $date,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    );

    public function getMyAttendances();

    public function getMyAttendanceStatistics();

    public function getById(string $id);

    public function getLastAttendanceByEmployee();

    public function checkIn(array $data);

    public function checkOut(array $data);

    public function getStatistics();
}
