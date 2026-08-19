<?php

namespace App\Repositories;

use App\DTOs\UserDto;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(private CloudinaryManager $cloudinary) {}

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
                $publicId = $this->cloudinary->uploadImage(
                    $data['profile_photo'],
                    CloudinaryFolders::companyFiles('employees'),
                    CloudinaryFolders::filename('user-'.$user->id.'-photo')
                );
                $user->update(['profile_photo' => $publicId]);
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
                $this->cloudinary->delete($user->profile_photo);

                $publicId = $this->cloudinary->uploadImage(
                    $data['profile_photo'],
                    CloudinaryFolders::companyFiles('employees'),
                    CloudinaryFolders::filename('user-'.$user->id.'-photo')
                );
                $user->update(['profile_photo' => $publicId]);
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
