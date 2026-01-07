<?php

namespace App\Repositories;

use App\DTOs\FilesCompanyDto;
use App\Interfaces\FilesCompanyRepositoryInterface;
use App\Models\FilesCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FilesCompanyRepository implements FilesCompanyRepositoryInterface
{
    public function statistics(): array
    {
        $total = FilesCompany::count();
        $lastUploaded = FilesCompany::orderByDesc('created_at')
            ->value('created_at'); // ambil timestamp terakhir

        return [
            'total_archives' => $total,
            'last_uploaded' => $lastUploaded ? $lastUploaded->toDateString() : null,
        ];
    }


    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = FilesCompany::query()
            ->when($search, fn($q) => $q->search($search))
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        return $execute ? $query->get() : $query;
    }

    public function getAllPaginated(?string $search, int $rowPerPage): LengthAwarePaginator
    {
        $query = $this->getAll($search, null, false);
        return $query->paginate($rowPerPage);
    }

    public function getById(string $id): FilesCompany
    {
        return FilesCompany::findOrFail($id);
    }

    public function create(array $data): FilesCompany
    {
        // DTO dibuat di repository
        $fileDto = FilesCompanyDto::fromArray($data);
        return FilesCompany::create($fileDto->toArray());
    }

    public function update(string $id, array $data): FilesCompany
    {
        $file = $this->getById($id);
        $fileDto = FilesCompanyDto::fromArrayForUpdate($data, $file);
        $file->update($fileDto->toArray());

        return $file;
    }

    public function delete(string $id): FilesCompany
    {
        $file = $this->getById($id);
        $file->delete();
        return $file;
    }
}
