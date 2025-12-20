<?php

namespace App\Repositories;

use App\DTOs\UserDto;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserRepository implements UserRepositoryInterface
{
    public function getById(string $id): User
    {
        return User::findOrFail($id)->load(['roles']);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $userDto = UserDto::fromArray($data);
            $user = User::create($userDto->toArray());

            if (isset($data['profile_photo'])) {
                $profilePhotoPath = $data['profile_photo']->store('users', 'public');
                $user->update(['profile_photo' => $profilePhotoPath]);
            }

            if (isset($data['roles'])) {
                $roles = collect($data['roles'])
                    ->map(function ($role) {
                        if (is_numeric($role)) {
                            return optional(Role::find($role))->name;
                        }

                        return $role;
                    })
                    ->filter()
                    ->all();
                $user->syncRoles($roles);
            }

            return $user;
        });
    }

    public function update(string $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $user = $this->getById($id);

            $userDto = UserDto::fromArrayForUpdate($data, $user);
            $user->update($userDto->toArray());

            if (isset($data['profile_photo'])) {
                if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                    Storage::disk('public')->delete($user->profile_photo);
                }

                $profilePhotoPath = $data['profile_photo']->store('users', 'public');
                $user->update(['profile_photo' => $profilePhotoPath]);
            }

            if (isset($data['roles'])) {
                $roles = collect($data['roles'])
                    ->map(function ($role) {
                        if (is_numeric($role)) {
                            return optional(Role::find($role))->name;
                        }

                        return $role;
                    })
                    ->filter()
                    ->all();
                $user->syncRoles($roles);
            }

            return $user;
        });
    }
}
