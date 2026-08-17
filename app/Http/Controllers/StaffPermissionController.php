<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\StaffAccountResource;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Permission;

class StaffPermissionController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['staff-permission-menu|staff-permission-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['staff-permission-edit']), only: ['update']),
        ];
    }

    /**
     * List every employee whose account has the "staff" role, so a manager
     * or HR can drill into one and grant it extra, account-specific
     * permissions on top of what the Staff role already gives everyone.
     */
    public function index(Request $request)
    {
        try {
            $query = EmployeeProfile::with(['user', 'jobInformation'])
                ->whereHas('user', fn ($q) => $q->role('staff'));

            if ($search = $request->input('search')) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $employees = $query->get();

            return ResponseHelper::jsonResponse(true, 'Staff Accounts Retrieved Successfully', StaffAccountResource::collection($employees), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Role-inherited permissions (read-only, shared by every Staff account)
     * versus this specific account's direct extra permissions (editable).
     */
    public function show(int $employeeId)
    {
        try {
            $employee = EmployeeProfile::with('user')->findOrFail($employeeId);
            $user = $employee->user;

            if (! $user || ! $user->hasRole('staff')) {
                return ResponseHelper::jsonResponse(false, 'This account is not a Staff account', null, 422);
            }

            return ResponseHelper::jsonResponse(true, 'Staff Permissions Retrieved Successfully', [
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => $user->profile_photo ? asset('storage/'.$user->profile_photo) : null,
                'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->values(),
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Replace this account's direct (non-role) permissions. Does not touch
     * the Staff role itself, so every other Staff account is unaffected.
     */
    public function update(Request $request, int $employeeId)
    {
        try {
            $validated = $request->validate([
                'permissions' => ['array'],
                'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
            ]);

            $employee = EmployeeProfile::with('user')->findOrFail($employeeId);
            $user = $employee->user;

            if (! $user || ! $user->hasRole('staff')) {
                return ResponseHelper::jsonResponse(false, 'This account is not a Staff account', null, 422);
            }

            $user->syncPermissions($validated['permissions'] ?? []);

            activity('Security')
                ->causedBy($request->user())
                ->performedOn($user)
                ->withProperties(['permissions' => $validated['permissions'] ?? []])
                ->event('staff_permission_updated')
                ->log("updated direct permissions for staff account \"{$user->name}\"");

            return ResponseHelper::jsonResponse(true, 'Staff Permissions Updated Successfully', [
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
