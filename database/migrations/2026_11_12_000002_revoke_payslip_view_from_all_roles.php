<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * General > My Payslips is now off by default for every role (see
     * RolePermissionSeeder). Revokes it from the existing roles; it can be
     * granted again per role in Roles & Permissions.
     */
    public function up(): void
    {
        $permission = Permission::where('name', 'payslip-view')->first();
        if (! $permission) {
            return;
        }

        foreach ($permission->roles()->get() as $role) {
            $role->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Which roles had it before isn't recorded; grant it again per role
        // in Roles & Permissions if needed.
    }
};
