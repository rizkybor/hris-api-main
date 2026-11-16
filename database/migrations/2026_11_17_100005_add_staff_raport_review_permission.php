<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Writing the optional closing note on a Staff Report: Manager,
     * Operational Director and Finance Manager; adjustable per role in Roles
     * & Permissions. New databases get it from PermissionSeeder.
     */
    private const ROLES = ['manager', 'operational_director', 'finance'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->distinct()->pluck('guard_name') as $guard) {
            $permissionId = DB::table('permissions')->where('name', 'staff-raport-review')->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => 'staff-raport-review', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);

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
        $ids = DB::table('permissions')->where('name', 'staff-raport-review')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
