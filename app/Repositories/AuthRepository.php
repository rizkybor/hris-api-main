<?php

namespace App\Repositories;

use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthRepository implements AuthRepositoryInterface
{
    public function login(array $data): User
    {
        DB::beginTransaction();

        try {
            if (! Auth::guard('web')->attempt($data)) {
                throw new \Exception('Unauthorized', 401);
            }

            $user = Auth::user()->load('roles');
            $user->token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function me(): User
    {
        if (! Auth::check()) {
            throw new \Exception('Unauthorized', 401);
        }

        $user = Auth::user()->load(['roles', 'permissions']);

        if ($user->hasRole('employee')) {
            $user->load('employeeProfile');
        }

        return $user;
    }

    public function logout(): User
    {
        if (! Auth::check()) {
            throw new \Exception('Unauthorized', 401);
        }

        $user = Auth::user();
        $user->tokens()->delete();

        return $user;
    }

    public function updateProfile(array $data): User
    {
        DB::beginTransaction();

        try {
            if (! Auth::check()) {
                throw new \Exception('Unauthorized', 401);
            }

            $user = Auth::user();

            if (isset($data['name'])) {
                $user->name = $data['name'];
            }

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if (isset($data['profile_photo'])) {
                if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                    Storage::disk('public')->delete($user->profile_photo);
                }

                $profilePhotoPath = $data['profile_photo']->store('profile-pictures', 'public');
                $user->profile_photo = $profilePhotoPath;
            }

            // Email is intentionally not updatable
            $user->save();

            DB::commit();

            return $user->fresh()->load(['roles', 'permissions']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
