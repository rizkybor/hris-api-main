<?php

namespace App\Interfaces;

interface ProjectRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?string $status,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        ?string $status,
        int $rowPerPage
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
}
