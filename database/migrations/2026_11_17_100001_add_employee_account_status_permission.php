<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Deactivating/reactivating an employee's login gets its own permission
     * (it was limited by role before). Granted to Superadmin, Manager, HR and
     * Operational Director; adjustable per role in Roles & Permissions. New
     * databases get it from PermissionSeeder.
     */
    private const ROLES = ['superadmin', 'manager', 'hr', 'operational_director'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $guards = DB::table('roles')->whereIn('name', self::ROLES)->distinct()->pluck('guard_name');

        foreach ($guards as $guard) {
            $permissionId = DB::table('permissions')->where('name', 'employee-account-status')->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => 'employee-account-status',
                    'guard_name' => $guard,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach (DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', $guard)->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->where('name', 'employee-account-status')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
