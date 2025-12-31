<?php

namespace App\Interfaces;

interface CompanyFinanceRepositoryInterface
{
    public function getAll(
        ?string $search = null,
        ?int $limit = null,
        bool $execute = true
    );

    public function getAllPaginated(
        ?string $search = null,
        int $rowPerPage = 15
    );

    public function getById(
        int $id
    );

    public function create(
        array $data
    );

    public function update(
        int $id,
        array $data
    );

    public function delete(
        int $id
    );
}