<?php

namespace App\Repositories;

use App\DTOs\VendorsTaskScopeDto;
use App\Interfaces\VendorsTaskScopeRepositoryInterface;
use App\Models\VendorsTaskScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorsTaskScopeRepository implements VendorsTaskScopeRepositoryInterface
{
    /**
     * Ambil semua task scope dengan filter search dan limit
     */
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = VendorsTaskScope::query()
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        return $execute ? $query->get() : $query;
    }

    /**
     * Ambil semua task scope dengan pagination
     */
    public function getAllPaginated(?string $search, int $rowPerPage): LengthAwarePaginator
    {
        $query = $this->getAll($search, null, false);

        return $query->paginate($rowPerPage);
    }

    /**
     * Ambil task scope berdasarkan ID
     */
    public function getById(string $id): VendorsTaskScope
    {
        return VendorsTaskScope::findOrFail($id);
    }

    /**
     * Buat task scope baru
     */
    public function create(array $data): VendorsTaskScope
    {
        return DB::transaction(function () use ($data) {
            $dto = VendorsTaskScopeDto::fromArray($data);
            $taskScope = VendorsTaskScope::create($dto->toArray());

            return $taskScope;
        });
    }

    /**
     * Update task scope
     */
    public function update(string $id, array $data): VendorsTaskScope
    {
        return DB::transaction(function () use ($id, $data) {
            $taskScope = $this->getById($id);
            $dto = VendorsTaskScopeDto::fromArrayForUpdate($data, $taskScope);

            $taskScope->update($dto->toArray());

            return $taskScope;
        });
    }

    /**
     * Hapus task scope
     */
    public function delete(string $id): VendorsTaskScope
    {
        return DB::transaction(function () use ($id) {
            $taskScope = $this->getById($id);
            $taskScope->delete();

            return $taskScope;
        });
    }
}
