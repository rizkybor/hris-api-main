<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * One module per menu, like "team": license-manager-menu / list / view /
     * create / edit / delete (products are managed with the same
     * permissions). Adds license-manager-view (one license, its events, the
     * certificate, the overview widgets) for every role that has
     * license-manager-list, and removes license-product-manage.
     */
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $guards = DB::table('permissions')->where('name', 'like', 'license-manager-%')->distinct()->pluck('guard_name');

        foreach ($guards as $guard) {
            $listId = DB::table('permissions')->where('name', 'license-manager-list')->where('guard_name', $guard)->value('id');
            $viewId = DB::table('permissions')->where('name', 'license-manager-view')->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => 'license-manager-view',
                    'guard_name' => $guard,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($listId) {
                foreach (DB::table('role_has_permissions')->where('permission_id', $listId)->pluck('role_id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $viewId, 'role_id' => $roleId]);
                }
            }
        }

        $manageIds = DB::table('permissions')->where('name', 'license-product-manage')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $manageIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $manageIds)->delete();
        DB::table('permissions')->whereIn('id', $manageIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->where('name', 'license-manager-view')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
