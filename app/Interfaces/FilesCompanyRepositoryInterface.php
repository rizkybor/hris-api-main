<?php

namespace App\Interfaces;

interface FilesCompanyRepositoryInterface
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

    /**
     * Get statistics of company files
     *
     * @return array{total_archives: int, last_uploaded: string|null}
     */
    public function statistics(): array;
}
