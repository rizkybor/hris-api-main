<?php

namespace App\Interfaces;

interface VendorsRepositoryInterface
{
    /**
     * Ambil semua vendor
     *
     * @param string|null $search
     * @param string|null $type
     * @param int|null $limit
     * @param bool $execute
     */
    public function getAll(
        ?string $search,
        ?string $type,
        ?int $limit,
        bool $execute
    );

    /**
     * Ambil semua vendor dengan pagination
     *
     * @param string|null $search
     * @param string|null $type
     * @param int $rowPerPage
     */
    public function getAllPaginated(
        ?string $search,
        ?string $type,
        int $rowPerPage
    );

    /**
     * Ambil vendor berdasarkan ID
     *
     * @param string $id
     */
    public function getById(
        string $id
    );

    /**
     * Buat vendor baru
     *
     * @param array $data
     */
    public function create(
        array $data
    );

    /**
     * Update vendor
     *
     * @param string $id
     * @param array $data
     */
    public function update(
        string $id,
        array $data
    );

    /**
     * Hapus vendor
     *
     * @param string $id
     */
    public function delete(
        string $id
    );
}
