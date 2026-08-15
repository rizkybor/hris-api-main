<?php

namespace App\Interfaces;

interface ProjectDocumentRepositoryInterface
{
    public function getByProjectId(int $projectId, ?string $search);

    public function getById(string $id);

    public function create(array $data);

    public function update(string $id, array $data);

    public function delete(string $id);
}
