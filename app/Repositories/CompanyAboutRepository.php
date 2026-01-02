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
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return CompanyAbout::all();
    }

    /**
     * Ambil Company About berdasarkan ID
     *
     * @param string $id
     * @return CompanyAbout
     */
    public function getById(string $id): CompanyAbout
    {
        return CompanyAbout::findOrFail($id);
    }

    /**
     * Buat Company About baru
     *
     * @param array $data
     * @return CompanyAbout
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
     *
     * @param string $id
     * @param array $data
     * @return CompanyAbout
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
     *
     * @param string $id
     * @return CompanyAbout
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
