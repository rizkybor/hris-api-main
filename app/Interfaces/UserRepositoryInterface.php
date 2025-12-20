<?php

namespace App\Interfaces;

interface UserRepositoryInterface
{
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
}
