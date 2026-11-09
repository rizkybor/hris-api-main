<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const RENAMES = [
        'occa-license-menu' => 'license-manager-menu',
        'occa-license-list' => 'license-manager-list',
        'occa-license-create' => 'license-manager-create',
        'occa-license-edit' => 'license-manager-edit',
        'occa-license-delete' => 'license-manager-delete',
    ];

    /**
     * Rename the permission rows in place so roles that already hold
     * occa-license-* keep them as license-manager-*. Also adds
     * license-product-manage and grants it to every role that can create
     * licenses (so the owner can manage products without re-seeding).
     */
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
        }

        foreach (DB::table('permissions')->where('name', 'license-manager-create')->get() as $create) {
            $manageId = DB::table('permissions')
                ->where('name', 'license-product-manage')
                ->where('guard_name', $create->guard_name)
                ->value('id');

            if (! $manageId) {
                $manageId = DB::table('permissions')->insertGetId([
                    'name' => 'license-product-manage',
                    'guard_name' => $create->guard_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roleIds = DB::table('role_has_permissions')->where('permission_id', $create->id)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $manageId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::RENAMES as $old => $new) {
            DB::table('permissions')->where('name', $new)->update(['name' => $old, 'updated_at' => now()]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
