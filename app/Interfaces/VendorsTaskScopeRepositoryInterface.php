<?php

namespace App\Interfaces;

interface VendorsTaskScopeRepositoryInterface
{
    /**
     * Ambil semua task scope vendor
     *
     * @param string|null $search
     * @param int|null $limit
     * @param bool $execute
     */
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    );

    /**
     * Ambil semua task scope vendor dengan pagination
     *
     * @param string|null $search
     * @param int $rowPerPage
     */
    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    );

    /**
     * Ambil task scope vendor berdasarkan ID
     *
     * @param string $id
     */
    public function getById(
        string $id
    );

    /**
     * Buat task scope vendor baru
     *
     * @param array $data
     */
    public function create(
        array $data
    );

    /**
     * Update task scope vendor
     *
     * @param string $id
     * @param array $data
     */
    public function update(
        string $id,
        array $data
    );

    /**
     * Hapus task scope vendor
     *
     * @param string $id
     */
    public function delete(
        string $id
    );
}
