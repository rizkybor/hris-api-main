<?php

namespace App\Interfaces;

interface VendorEvaluationRepositoryInterface
{
    public function getByVendor(string $vendorId);

    public function create(array $data);

    public function delete(string $id): void;
}
