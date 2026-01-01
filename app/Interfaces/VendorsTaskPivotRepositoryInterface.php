<?php

namespace App\Interfaces;

interface VendorsTaskPivotRepositoryInterface
{
    /**
     * Ambil semua task pivot vendor
     *
     * @param string|null $search
     * @param int|null $vendorId
     * @param int|null $limit
     * @param bool $execute
     */
    public function getAll(
        ?string $search,
        ?int $vendorId,
        ?int $limit,
        bool $execute
    );

    /**
     * Ambil semua task pivot vendor dengan pagination
     *
     * @param string|null $search
     * @param int|null $vendorId
     * @param int $rowPerPage
     */
    public function getAllPaginated(
        ?string $search,
        ?int $vendorId,
        int $rowPerPage
    );

    /**
     * Ambil task pivot vendor berdasarkan ID
     *
     * @param string $id
     */
    public function getById(
        string $id
    );

    /**
     * Buat task pivot vendor baru
     *
     * @param array $data
     */
    public function create(
        array $data
    );

    /**
     * Update task pivot vendor
     *
     * @param string $id
     * @param array $data
     */
    public function update(
        string $id,
        array $data
    );

    /**
     * Hapus task pivot vendor
     *
     * @param string $id
     */
    public function delete(
        string $id
    );

    /**
     * Ambil semua task pivot berdasarkan vendor
     *
     * @param int $vendorId
     */
    public function getByVendorId(
        int $vendorId
    );
}