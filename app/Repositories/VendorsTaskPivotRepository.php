<?php

namespace App\Repositories;

use App\DTOs\VendorsTaskPivotDto;
use App\Interfaces\VendorsTaskPivotRepositoryInterface;
use App\Models\VendorsTaskPivot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorsTaskPivotRepository implements VendorsTaskPivotRepositoryInterface
{
    /**
     * Ambil semua vendor task pivot dengan filter search, vendorId, dan limit
     */
    public function getAll(?string $search, ?int $vendorId, ?int $limit, bool $execute): Builder|Collection
    {
        $query = VendorsTaskPivot::with(['vendor', 'scopeVendor', 'taskVendor', 'taskPayment'])
            ->when($search, fn($q) => $q->whereHas('vendor', fn($q2) => $q2->search($search)))
            ->when($vendorId, fn($q) => $q->where('vendor_id', $vendorId))
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        return $execute ? $query->get() : $query;
    }

    /**
     * Ambil semua vendor task pivot dengan pagination
     */
    public function getAllPaginated(?string $search, ?int $vendorId, int $rowPerPage): LengthAwarePaginator
    {
        $query = $this->getAll($search, $vendorId, null, false);

        return $query->paginate($rowPerPage);
    }

    /**
     * Ambil vendor task pivot berdasarkan ID
     */
    public function getById(string $id): VendorsTaskPivot
    {
        return VendorsTaskPivot::with(['vendor', 'scopeVendor', 'taskVendor', 'taskPayment'])
            ->findOrFail($id);
    }

    /**
     * Buat vendor task pivot baru
     */
    public function create(array $data): VendorsTaskPivot
    {
        return DB::transaction(function () use ($data) {
            $dto = VendorsTaskPivotDto::fromArray($data);
            return VendorsTaskPivot::create($dto->toArray());
        });
    }

    /**
     * Update vendor task pivot
     */
    public function update(string $id, array $data): VendorsTaskPivot
    {
        return DB::transaction(function () use ($id, $data) {
            $pivot = $this->getById($id);
            $dto = VendorsTaskPivotDto::fromArrayForUpdate($data, $pivot);

            $pivot->update($dto->toArray());

            return $pivot;
        });
    }

    /**
     * Hapus vendor task pivot
     */
    public function delete(string $id): VendorsTaskPivot
    {
        return DB::transaction(function () use ($id) {
            $pivot = $this->getById($id);
            $pivot->delete();

            return $pivot;
        });
    }

    /**
     * Ambil semua task pivot berdasarkan vendor
     */
    public function getByVendorId(int $vendorId): Collection
    {
        return VendorsTaskPivot::with(['vendor', 'scopeVendor', 'taskVendor', 'taskPayment'])
            ->where('vendor_id', $vendorId)
            ->get();
    }
}
