<?php

namespace App\Repositories;

use App\DTOs\ClientTaskScopeDto;
use App\Interfaces\ClientTaskScopeRepositoryInterface;
use App\Models\ClientTaskScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientTaskScopeRepository implements ClientTaskScopeRepositoryInterface
{
    /**
     * Ambil semua task scope dengan filter search dan limit
     */
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = ClientTaskScope::query()
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
    public function getById(string $id): ClientTaskScope
    {
        return ClientTaskScope::findOrFail($id);
    }

    /**
     * Buat task scope baru
     */
    public function create(array $data): ClientTaskScope
    {
        return DB::transaction(function () use ($data) {
            $dto = ClientTaskScopeDto::fromArray($data);
            $taskScope = ClientTaskScope::create($dto->toArray());

            return $taskScope;
        });
    }

    /**
     * Update task scope
     */
    public function update(string $id, array $data): ClientTaskScope
    {
        return DB::transaction(function () use ($id, $data) {
            $taskScope = $this->getById($id);
            $dto = ClientTaskScopeDto::fromArrayForUpdate($data, $taskScope);

            $taskScope->update($dto->toArray());

            return $taskScope;
        });
    }

    /**
     * Hapus task scope
     */
    public function delete(string $id): ClientTaskScope
    {
        return DB::transaction(function () use ($id) {
            $taskScope = $this->getById($id);
            $taskScope->delete();

            return $taskScope;
        });
    }
}
