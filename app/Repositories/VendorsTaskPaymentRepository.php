<?php

namespace App\Repositories;

use App\DTOs\VendorsTaskPaymentDto;
use App\Interfaces\VendorsTaskPaymentRepositoryInterface;
use App\Models\VendorsTaskPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorsTaskPaymentRepository implements VendorsTaskPaymentRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute): Builder|Collection
    {
        $query = VendorsTaskPayment::query()
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
        return VendorsTaskPayment::findOrFail($id);
    }

    public function getByVendorTaskId(int $vendorTaskId): Collection
    {
        return VendorsTaskPayment::where('vendor_task_id', $vendorTaskId)->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $dto = VendorsTaskPaymentDto::fromArray($data);
            return VendorsTaskPayment::create($dto->toArray());
        });
    }

    public function update(string $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $taskPayment = $this->getById($id);
            $dto = VendorsTaskPaymentDto::fromArrayForUpdate($data, $taskPayment);

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
