<?php

namespace App\Interfaces;

interface VendorsTaskPaymentRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $vendorTaskId,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        ?int $vendorTaskId,
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

    public function getByVendorTaskId(
        int $vendorTaskId
    );
}
