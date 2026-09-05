<?php

namespace App\Repositories;

use App\DTOs\ClientTaskPivotDto;
use App\Interfaces\ClientTaskPivotRepositoryInterface;
use App\Models\ClientTaskPivot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientTaskPivotRepository implements ClientTaskPivotRepositoryInterface
{
    /**
     * Ambil semua client task pivot dengan filter search, clientId, dan limit
     */
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = ClientTaskPivot::with(['client', 'scopeClient', 'taskClient', 'paymentClient'])
            ->when($search, fn($q) => $q->whereHas('client', fn($q2) => $q2->search($search)))
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        return $execute ? $query->get() : $query;
    }

    /**
     * Ambil semua client task pivot dengan pagination
     */
    public function getAllPaginated(?string $search, int $rowPerPage): LengthAwarePaginator
    {
        $query = $this->getAll($search, null, false);

        return $query->paginate($rowPerPage);
    }

    /**
     * Ambil client task pivot berdasarkan ID
     */
    public function getById(string $id): ClientTaskPivot
    {
        return ClientTaskPivot::with(['client', 'scopeClient', 'taskClient', 'paymentClient'])
            ->findOrFail($id);
    }

    /**
     * Buat client task pivot baru
     */
    public function create(array $data): ClientTaskPivot
    {
        return DB::transaction(function () use ($data) {
            $dto = ClientTaskPivotDto::fromArray($data);
            return ClientTaskPivot::create($dto->toArray());
        });
    }

    /**
     * Update client task pivot
     */
    public function update(string $id, array $data): ClientTaskPivot
    {
        return DB::transaction(function () use ($id, $data) {
            $pivot = $this->getById($id);
            $dto = ClientTaskPivotDto::fromArrayForUpdate($data, $pivot);

            $pivot->update($dto->toArray());

            return $pivot;
        });
    }

    /**
     * Hapus client task pivot
     */
    public function delete(string $id): ClientTaskPivot
    {
        return DB::transaction(function () use ($id) {
            $pivot = $this->getById($id);
            $pivot->delete();

            return $pivot;
        });
    }

    /**
     * Ambil semua task pivot berdasarkan client
     */
    public function getByClientId(int $clientId): Collection
    {
        return ClientTaskPivot::with(['client', 'scopeClient', 'taskClient', 'paymentClient'])
            ->where('client_id', $clientId)
            ->get();
    }
}
