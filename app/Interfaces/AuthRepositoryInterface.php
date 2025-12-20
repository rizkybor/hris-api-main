<?php

namespace App\Interfaces;

interface AuthRepositoryInterface
{
    public function login(
        array $data
    );

    public function me();

    public function logout();

    public function updateProfile(array $data);
}
