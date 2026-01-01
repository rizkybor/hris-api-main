<?php

namespace App\Repositories;

use App\DTOs\VendorsTaskListDto;
use App\Interfaces\VendorsTaskListRepositoryInterface;
use App\Models\VendorsTaskList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorsTaskListRepository implements VendorsTaskListRepositoryInterface
{
    /**
     * Ambil semua task list dengan filter search dan limit
     */
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = VendorsTaskList::query()
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
    public function getById(string $id): VendorsTaskList
    {
        return VendorsTaskList::findOrFail($id);
    }

    /**
     * Buat task list baru
     */
    public function create(array $data): VendorsTaskList
    {
        return DB::transaction(function () use ($data) {
            $dto = VendorsTaskListDto::fromArray($data);
            $taskList = VendorsTaskList::create($dto->toArray());

            return $taskList;
        });
    }

    /**
     * Update task list
     */
    public function update(string $id, array $data): VendorsTaskList
    {
        return DB::transaction(function () use ($id, $data) {
            $taskList = $this->getById($id);
            $dto = VendorsTaskListDto::fromArrayForUpdate($data, $taskList);

            $taskList->update($dto->toArray());

            return $taskList;
        });
    }

    /**
     * Hapus task list
     */
    public function delete(string $id): VendorsTaskList
    {
        return DB::transaction(function () use ($id) {
            $taskList = $this->getById($id);
            $taskList->delete();

            return $taskList;
        });
    }
}
