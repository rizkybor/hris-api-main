<?php

namespace App\Repositories;

use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthRepository implements AuthRepositoryInterface
{
    public function __construct(private CloudinaryManager $cloudinary) {}

    public function login(array $data): User
    {
        DB::beginTransaction();

        try {
            if (! Auth::guard('web')->attempt($data)) {
                throw new \Exception('Unauthorized', 401);
            }

            /** @var User $user */
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::guard('web')->logout();

                throw new \Exception('Your account has been deactivated. Please contact your administrator.', 403);
            }

            $user->load(['roles', 'permissions']);
            $user->token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function me(): User
    {
        if (! Auth::check()) {
            throw new \Exception('Unauthorized', 401);
        }

        /** @var User $user */
        $user = Auth::user();
        // employeeProfile is loaded for every role, not just staff -- e.g.
        // Manager/HR/Operational Director/Finance need their own employee_id
        // client-side too (to exclude themselves from @mention suggestions
        // on Meeting Note comments). The deeper .jobInformation stays
        // staff-only since only the staff "My Workspace" widgets use it.
        $user->load(['roles', 'permissions', 'employeeProfile']);

        if ($user->hasRole('staff')) {
            $user->load('employeeProfile.jobInformation');
        }

        return $user;
    }

    public function logout(): User
    {
        if (! Auth::check()) {
            throw new \Exception('Unauthorized', 401);
        }

        /** @var User $user */
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

            /** @var User $user */
            $user = Auth::user();

            if (isset($data['name'])) {
                $user->name = $data['name'];
            }

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if (isset($data['profile_photo'])) {
                $this->cloudinary->delete($user->profile_photo);

                // Same company-files/employees folder as UserRepository's
                // admin-side photo upload -- these two were previously
                // pointed at different local folders (profile-pictures/ vs
                // users/) for the same profile_photo column, unified here.
                $publicId = $this->cloudinary->uploadImage(
                    $data['profile_photo'],
                    CloudinaryFolders::companyFiles('employees'),
                    CloudinaryFolders::filename('user-'.$user->id.'-photo')
                );
                $user->profile_photo = $publicId;
            }

            // Email is intentionally not updatable
            $user->save();

            DB::commit();

            /** @var User $freshUser */
            $freshUser = $user->fresh();
            $freshUser->load(['roles', 'permissions']);

            return $freshUser;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
