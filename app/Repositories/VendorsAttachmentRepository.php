<?php

namespace App\Repositories;

use App\DTOs\VendorsAttachmentDto;
use App\Models\VendorsAttachment;
use App\Interfaces\VendorsAttachmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VendorsAttachmentRepository implements VendorsAttachmentRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = VendorsAttachment::query()
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
    ): VendorsAttachment {
        return VendorsAttachment::findOrFail($id);
    }

    public function getStatisticByVendor(
        string $vendorId,
        ?string $search = null
    ): array {
        $query = VendorsAttachment::query()
            ->where('vendor_id', $vendorId);

        if ($search) {
            $query->search($search);
        }

        $items = $query->get();

        $totalFiles = $items->count();

        $totalSize = $items->sum(function ($item) {
            return (float) $item->size_file;
        });

        return [
            'vendor_id' => $vendorId,
            'items' => $items,
            'summary' => [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
            ],
        ];
    }



    public function create(
        array $data
    ): VendorsAttachment {
        $fileDto = VendorsAttachmentDto::fromArray($data);
        $fileArray = $fileDto->toArray();

        return VendorsAttachment::create($fileArray);
    }

    public function update(
        string $id,
        array $data
    ): VendorsAttachment {
        $file = $this->getById($id);
        $fileDto = VendorsAttachmentDto::fromArrayForUpdate($data, $file);
        $file->update($fileDto->toArray());

        return $file;
    }

    public function delete(
        string $id
    ): VendorsAttachment {
        $file = $this->getById($id);
        $file->delete();

        return $file;
    }
}
