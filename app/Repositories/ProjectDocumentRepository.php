<?php

namespace App\Repositories;

use App\DTOs\ProjectDocumentDto;
use App\Interfaces\ProjectDocumentRepositoryInterface;
use App\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Collection;

class ProjectDocumentRepository implements ProjectDocumentRepositoryInterface
{
    public function getByProjectId(int $projectId, ?string $search): Collection
    {
        return ProjectDocument::query()
            ->where('project_id', $projectId)
            ->when($search, fn ($query) => $query->search($search))
            ->orderByDesc('created_at')
            ->get();
    }

    public function getById(string $id): ProjectDocument
    {
        return ProjectDocument::findOrFail($id);
    }

    public function create(array $data): ProjectDocument
    {
        $dto = ProjectDocumentDto::fromArray($data);

        return ProjectDocument::create($dto->toArray());
    }

    public function update(string $id, array $data): ProjectDocument
    {
        $document = $this->getById($id);
        $dto = ProjectDocumentDto::fromArrayForUpdate($data, $document);
        $document->update($dto->toArray());

        return $document;
    }

    public function delete(string $id): ProjectDocument
    {
        $document = $this->getById($id);
        $document->delete();

        return $document;
    }
}
