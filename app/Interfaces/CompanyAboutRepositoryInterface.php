<?php

namespace App\Interfaces;

interface CompanyAboutRepositoryInterface
{
    /**
     * Ambil semua data company about
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll();

    /**
     * Ambil data company about berdasarkan ID
     *
     * @param string $id
     */
    public function getById(
        string $id
    );

    /**
     * Buat data company about baru
     *
     * @param array $data
     */
    public function create(
        array $data
    );

    /**
     * Update data company about
     *
     * @param string $id
     * @param array $data
     */
    public function update(
        string $id,
        array $data
    );

    /**
     * Hapus data company about
     *
     * @param string $id
     */
    public function delete(
        string $id
    );
}
