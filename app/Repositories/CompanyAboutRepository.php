<?php

namespace App\Repositories;

use App\DTOs\CompanyAboutDto;
use App\Interfaces\CompanyAboutRepositoryInterface;
use App\Models\CompanyAbout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CompanyAboutRepository implements CompanyAboutRepositoryInterface
{
    /**
     * Ambil semua Company About
     */
    public function getAll(): Collection
    {
        return CompanyAbout::all();
    }

    /**
     * Ambil Company About berdasarkan ID
     */
    public function getById(string $id): CompanyAbout
    {
        return CompanyAbout::findOrFail($id);
    }

    /**
     * Buat Company About baru
     */
    public function create(array $data): CompanyAbout
    {
        return DB::transaction(function () use ($data) {
            $dto = CompanyAboutDto::fromArray($data);

            return CompanyAbout::create($dto->toArray());
        });
    }

    /**
     * Update Company About
     */
    public function update(string $id, array $data): CompanyAbout
    {
        return DB::transaction(function () use ($id, $data) {
            $companyAbout = $this->getById($id);
            $dto = CompanyAboutDto::fromArrayForUpdate($data, $companyAbout);

            $companyAbout->update($dto->toArray());

            return $companyAbout;
        });
    }

    /**
     * Hapus Company About
     */
    public function delete(string $id): CompanyAbout
    {
        return DB::transaction(function () use ($id) {
            $companyAbout = $this->getById($id);
            $companyAbout->delete();

            return $companyAbout;
        });
    }
}
