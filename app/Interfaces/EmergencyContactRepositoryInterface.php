<?php

namespace App\Interfaces;

interface EmergencyContactRepositoryInterface
{
    public function getById(string $id);

    public function create(array $data);

    public function update(string $id, array $data);

    public function delete(string $id);
}
