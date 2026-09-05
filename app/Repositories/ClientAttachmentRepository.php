<?php

namespace App\Repositories;

use App\DTOs\ClientAttachmentDto;
use App\Models\ClientAttachment;
use App\Interfaces\ClientAttachmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClientAttachmentRepository implements ClientAttachmentRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = ClientAttachment::query()
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
                }
            })
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): ClientAttachment {
        return ClientAttachment::findOrFail($id);
    }

    public function getStatisticByClient(
        string $clientId,
        ?string $search = null
    ): array {
        $query = ClientAttachment::query()
            ->where('client_id', $clientId);

        if ($search) {
            $query->search($search);
        }

        $items = $query->get();

        $totalFiles = $items->count();

        $totalSize = $items->sum(function ($item) {
            return (float) $item->size_file;
        });

        return [
            'client_id' => $clientId,
            'items' => $items,
            'summary' => [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
            ],
        ];
    }



    public function create(
        array $data
    ): ClientAttachment {
        $fileDto = ClientAttachmentDto::fromArray($data);
        $fileArray = $fileDto->toArray();

        return ClientAttachment::create($fileArray);
    }

    public function update(
        string $id,
        array $data
    ): ClientAttachment {
        $file = $this->getById($id);
        $fileDto = ClientAttachmentDto::fromArrayForUpdate($data, $file);
        $file->update($fileDto->toArray());

        return $file;
    }

    public function delete(
        string $id
    ): ClientAttachment {
        $file = $this->getById($id);
        $file->delete();

        return $file;
    }
}
