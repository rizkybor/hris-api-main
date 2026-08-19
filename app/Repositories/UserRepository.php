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
        $user = DB::transaction(function () use ($data) {
            $userDto = UserDto::fromArray($data);
            $user = User::create($userDto->toArray());

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

        // Uploaded outside the transaction (see ProjectRepository::create()
        // for the same reasoning) so a slow/failed Cloudinary call can't
        // hold DB locks open or force a rollback of the user creation.
        if (isset($data['profile_photo'])) {
            $publicId = $this->cloudinary->uploadImage(
                $data['profile_photo'],
                CloudinaryFolders::companyFiles('employees'),
                CloudinaryFolders::filename('user-'.$user->id.'-photo')
            );
            $user->update(['profile_photo' => $publicId]);
        }

        return $user;
    }

    public function update(string $id, array $data): User
    {
        $user = DB::transaction(function () use ($id, $data) {
            $user = $this->getById($id);

            $userDto = UserDto::fromArrayForUpdate($data, $user);
            $user->update($userDto->toArray());

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

        if (isset($data['profile_photo'])) {
            $oldPhoto = $user->profile_photo;

            $publicId = $this->cloudinary->uploadImage(
                $data['profile_photo'],
                CloudinaryFolders::companyFiles('employees'),
                CloudinaryFolders::filename('user-'.$user->id.'-photo')
            );
            $user->update(['profile_photo' => $publicId]);

            if ($oldPhoto) {
                $this->cloudinary->delete($oldPhoto);
            }
        }

        return $user;
    }
}
