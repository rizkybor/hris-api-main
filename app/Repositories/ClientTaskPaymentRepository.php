<?php

namespace App\Repositories;

use App\DTOs\ClientTaskPaymentDto;
use App\Interfaces\ClientTaskPaymentRepositoryInterface;
use App\Models\ClientTaskPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientTaskPaymentRepository implements ClientTaskPaymentRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = ClientTaskPayment::query()
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

    public function getById(string $id)
    {
        return ClientTaskPayment::findOrFail($id);
    }

    public function getByClientTaskId(int $clientTaskId): Collection
    {
        return ClientTaskPayment::where('client_task_id', $clientTaskId)->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $dto = ClientTaskPaymentDto::fromArray($data);
            return ClientTaskPayment::create($dto->toArray());
        });
    }

    public function update(string $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $taskPayment = $this->getById($id);
            $dto = ClientTaskPaymentDto::fromArrayForUpdate($data, $taskPayment);

            $taskPayment->update($dto->toArray());

            return $taskPayment;
        });
    }

    public function delete(string $id)
    {
        return DB::transaction(function () use ($id) {
            $taskPayment = $this->getById($id);
            $taskPayment->delete();

            return $taskPayment;
        });
    }
}
