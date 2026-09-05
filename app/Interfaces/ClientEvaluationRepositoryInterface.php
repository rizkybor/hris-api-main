<?php

namespace App\Interfaces;

interface ClientEvaluationRepositoryInterface
{
    public function getByClient(string $clientId);

    public function create(array $data);

    public function delete(string $id): void;
}
