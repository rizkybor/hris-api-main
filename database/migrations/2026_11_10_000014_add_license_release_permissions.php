<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * License Manager › Versions has its own permission module, like every
     * menu: license-release-menu / list / view / create / edit / delete /
     * download. Every role (and user) with a license-manager permission gets
     * the matching license-release one, so nobody loses access; download
     * follows license-manager-view. New databases get them from
     * PermissionSeeder.
     */
    private const ACTIONS = [
        'menu' => 'menu',
        'list' => 'list',
        'view' => 'view',
        'create' => 'create',
        'edit' => 'edit',
        'delete' => 'delete',
        'download' => 'view',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $guards = DB::table('permissions')->where('name', 'like', 'license-manager-%')->distinct()->pluck('guard_name');

        foreach ($guards as $guard) {
            foreach (self::ACTIONS as $action => $from) {
                $newId = DB::table('permissions')->where('name', "license-release-{$action}")->where('guard_name', $guard)->value('id')
                    ?? DB::table('permissions')->insertGetId([
                        'name' => "license-release-{$action}",
                        'guard_name' => $guard,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                $fromId = DB::table('permissions')->where('name', "license-manager-{$from}")->where('guard_name', $guard)->value('id');
                if (! $fromId) {
                    continue;
                }

                foreach (DB::table('role_has_permissions')->where('permission_id', $fromId)->pluck('role_id') as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]);
                }
                foreach (DB::table('model_has_permissions')->where('permission_id', $fromId)->get() as $row) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $newId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->where('name', 'like', 'license-release-%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
