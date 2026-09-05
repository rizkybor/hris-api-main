<?php

namespace App\Http\Controllers;

use App\Constants\PermissionModules;
use App\Helpers\ResponseHelper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['role-menu|role-view']), only: ['index', 'show', 'permissions']),
            new Middleware(PermissionMiddleware::using(['role-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['role-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['role-delete']), only: ['destroy']),
        ];
    }

    public function index()
    {
        try {
            $roles = Role::withCount('users')->with('permissions:id,name')->get();

            return ResponseHelper::jsonResponse(true, 'Roles Retrieved Successfully', $roles, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function permissions()
    {
        try {
            $permissions = Permission::orderBy('name')->get(['id', 'name']);

            $grouped = $permissions
                ->groupBy(fn ($permission) => PermissionModules::resolve($permission->name))
                ->map(function ($permissions, $moduleKey) {
                    $section = PermissionModules::section($moduleKey);

                    return [
                        'key' => $moduleKey,
                        'label' => PermissionModules::label($moduleKey),
                        'section' => $section,
                        'section_order' => PermissionModules::sectionOrderIndex($section),
                        'permissions' => $permissions->map(fn ($permission) => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $this->actionLabel($moduleKey, $permission->name),
                        ])->values(),
                    ];
                })
                // Grouped the same way the sidebar itself is grouped (General,
                // My Workspace, Administration, ...) instead of one flat A-Z
                // list of ~40 modules, so assigning a role reads the same way
                // as the app's own navigation.
                ->sortBy([['section_order', 'asc'], ['label', 'asc']])
                ->values();

            return ResponseHelper::jsonResponse(true, 'Permissions Retrieved Successfully', $grouped, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Turn a permission's action suffix (e.g. "menu", "check-in", "my-attendances")
     * into a short, human-readable label for the permission matrix UI.
     */
    private function actionLabel(string $moduleKey, string $permissionName): string
    {
        $suffix = str_starts_with($permissionName, $moduleKey.'-')
            ? substr($permissionName, strlen($moduleKey) + 1)
            : $permissionName;

        $knownLabels = [
            'menu' => 'Menu Access',
            'list' => 'List',
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'export' => 'Export',
            'process' => 'Process',
            'approve' => 'Approve',
            'statistic' => 'Statistics',
            'statistics' => 'Statistics',
            'check-in' => 'Check In',
            'check-out' => 'Check Out',
            'last-attendance' => 'Last Attendance',
            'my-attendances' => 'My Attendances',
            'my-statistics' => 'My Statistics',
            'my-requests' => 'My Requests',
        ];

        return $knownLabels[$suffix] ?? ucwords(str_replace('-', ' ', $suffix));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        try {
            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'sanctum']);

            if (! empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            activity('Security')
                ->causedBy($request->user())
                ->withProperties(['role' => $role->name, 'permissions' => $validated['permissions'] ?? []])
                ->event('role_created')
                ->log("created role \"{$role->name}\"");

            return ResponseHelper::jsonResponse(true, 'Role Created Successfully', $role->load('permissions:id,name'), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $role = Role::with('permissions:id,name')->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Role Retrieved Successfully', $role, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Role Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $role = Role::findOrFail($id);

            if ($role->name === 'superadmin') {
                return ResponseHelper::jsonResponse(false, 'The Super Admin role cannot be edited. Change it directly in the database if it is ever truly necessary.', null, 403);
            }

            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
                'permissions' => ['array'],
                'permissions.*' => ['string', 'exists:permissions,name'],
            ]);

            if (isset($validated['name'])) {
                $role->update(['name' => $validated['name']]);
            }

            if (array_key_exists('permissions', $validated)) {
                $role->syncPermissions($validated['permissions']);
            }

            activity('Security')
                ->causedBy($request->user())
                ->withProperties(['role' => $role->name, 'permissions' => $validated['permissions'] ?? null])
                ->event('role_updated')
                ->log("updated role \"{$role->name}\"");

            return ResponseHelper::jsonResponse(true, 'Role Updated Successfully', $role->fresh()->load('permissions:id,name'), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Role Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $role = Role::withCount('users')->findOrFail($id);

            if ($role->name === 'superadmin') {
                return ResponseHelper::jsonResponse(false, 'The Super Admin role cannot be deleted. Remove it directly in the database if it is ever truly necessary.', null, 403);
            }

            if ($role->users_count > 0) {
                return ResponseHelper::jsonResponse(false, 'Cannot delete a role that is still assigned to users', null, 422);
            }

            $roleName = $role->name;
            $role->delete();

            activity('Security')
                ->causedBy($request->user())
                ->withProperties(['role' => $roleName])
                ->event('role_deleted')
                ->log("deleted role \"{$roleName}\"");

            return ResponseHelper::jsonResponse(true, 'Role Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Role Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
