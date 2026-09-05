<?php

namespace App\Repositories;

use App\DTOs\ClientTaskListDto;
use App\Interfaces\ClientTaskListRepositoryInterface;
use App\Models\ClientTaskList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientTaskListRepository implements ClientTaskListRepositoryInterface
{
    /**
     * Ambil semua task list dengan filter search dan limit
     */
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = ClientTaskList::query()
            ->when($search, fn($q) => $q->search($search))
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        return $execute ? $query->get() : $query;
    }

    /**
     * Ambil semua task list dengan pagination
     */
    public function getAllPaginated(?string $search, int $rowPerPage): LengthAwarePaginator
    {
        $query = $this->getAll($search, null, false);

        return $query->paginate($rowPerPage);
    }

    /**
     * Ambil task list berdasarkan ID
     */
    public function getById(string $id): ClientTaskList
    {
        return ClientTaskList::findOrFail($id);
    }

    /**
     * Buat task list baru
     */
    public function create(array $data): ClientTaskList
    {
        return DB::transaction(function () use ($data) {
            $dto = ClientTaskListDto::fromArray($data);
            $taskList = ClientTaskList::create($dto->toArray());

            return $taskList;
        });
    }

    /**
     * Update task list
     */
    public function update(string $id, array $data): ClientTaskList
    {
        return DB::transaction(function () use ($id, $data) {
            $taskList = $this->getById($id);
            $dto = ClientTaskListDto::fromArrayForUpdate($data, $taskList);

            $taskList->update($dto->toArray());

            return $taskList;
        });
    }

    /**
     * Hapus task list
     */
    public function delete(string $id): ClientTaskList
    {
        return DB::transaction(function () use ($id) {
            $taskList = $this->getById($id);
            $taskList->delete();

            return $taskList;
        });
    }
}
