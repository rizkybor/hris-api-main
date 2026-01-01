<?php

namespace App\Interfaces;

interface VendorsTaskListRepositoryInterface
{
    /**
     * Ambil semua task list vendor
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
     * Ambil semua task list vendor dengan pagination
     *
     * @param string|null $search
     * @param int $rowPerPage
     */
    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    );

    /**
     * Ambil task list vendor berdasarkan ID
     *
     * @param string $id
     */
    public function getById(
        string $id
    );

    /**
     * Buat task list vendor baru
     *
     * @param array $data
     */
    public function create(
        array $data
    );

    /**
     * Update task list vendor
     *
     * @param string $id
     * @param array $data
     */
    public function update(
        string $id,
        array $data
    );

    /**
     * Hapus task list vendor
     *
     * @param string $id
     */
    public function delete(
        string $id
    );
}
