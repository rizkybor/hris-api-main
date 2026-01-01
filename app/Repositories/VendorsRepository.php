<?php

namespace App\Repositories;

use App\DTOs\VendorsDto;
use App\Interfaces\VendorsRepositoryInterface;
use App\Models\Vendors;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorsRepository implements VendorsRepositoryInterface
{
    /**
     * Ambil semua vendor dengan filter search dan limit
     */
    public function getAll(?string $search, ?string $type, ?int $limit, bool $execute): Builder|Collection
    {
        $query = Vendors::with(['vendorTasks.scopeVendor', 'vendorTasks.taskVendor', 'vendorTasks.taskPayment'])
            ->when($search, fn($q) => $q->search($search))
            ->when($type, fn($q) => $q->where('type', $type))
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        return $execute ? $query->get() : $query;
    }


    /**
     * Ambil semua vendor dengan pagination
     */
    public function getAllPaginated(?string $search, ?string $type, int $rowPerPage): LengthAwarePaginator
{
    $query = $this->getAll($search, $type, null, false);

    return $query->paginate($rowPerPage);
}


    /**
     * Ambil vendor berdasarkan ID
     */
    public function getById(string $id): Vendors
    {
        return Vendors::with([
            'vendorTasks.scopeVendor',
            'vendorTasks.taskVendor',
            'vendorTasks.taskPayment',
        ])->findOrFail($id);
    }

    /**
     * Buat vendor baru
     */
    public function create(array $data): Vendors
    {
        return DB::transaction(function () use ($data) {
            $vendorDto = VendorsDto::fromArray($data);
            $vendor = Vendors::create($vendorDto->toArray());

            return $vendor;
        });
    }

    /**
     * Update vendor
     */
    public function update(string $id, array $data): Vendors
    {
        return DB::transaction(function () use ($id, $data) {
            $vendor = $this->getById($id);

            $vendorDto = VendorsDto::fromArrayForUpdate($data, $vendor);
            $vendor->update($vendorDto->toArray());

            return $vendor;
        });
    }

    /**
     * Hapus vendor
     */
    public function delete(string $id): Vendors
    {
        return DB::transaction(function () use ($id) {
            $vendor = $this->getById($id);
            $vendor->delete();

            return $vendor;
        });
    }
}
