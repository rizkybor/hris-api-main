<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    private $permissions = [
        'dashboard' => [
            'menu',
            'view',
        ],

        'profile' => [
            'menu',
            'view',
        ],

        'team' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'view',
        ],

        'employee' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'project' => [
            'menu',
            'statistic',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'task' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'attendance' => [
            'menu',
            'list',
            'my-attendances',
            'my-statistics',
            'check-in',
            'check-out',
            'last-attendance',
        ],

        'leave-request' => [
            'menu',
            'list',
            'create',
            'approve',
            'my-requests',
        ],

        'payroll' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'process',
            'statistics',
        ],

        'company-about' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'credential-account' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'company-finance' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'statistic'
        ],

        'fixed-cost' => [
            'list',
            'create',
            'edit',
            'delete',
        ],

        'infrastructure-tool' => [
            'list',
            'create',
            'edit',
            'delete',
        ],

        'sdm-resource' => [
            'list',
            'create',
            'edit',
            'delete',
        ],

        'files-company' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'vendors' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'attachment-list',
            'attachment-create',
            'attachment-edit',
            'attachment-delete',
            'task-list',
            'task-list-create',
            'task-list-edit',
            'task-list-delete',
            'task-scope-list',
            'task-scope-create',
            'task-scope-edit',
            'task-scope-delete',
            'task-payment-list',
            'task-payment-create',
            'task-payment-edit',
            'task-payment-delete',
            'task-pivot-list',
            'task-pivot-create',
            'task-pivot-edit',
            'task-pivot-delete',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->permissions as $key => $value) {
            foreach ($value as $permission) {
                Permission::firstOrCreate([
                    'name' => $key.'-'.$permission,
                    'guard_name' => 'sanctum',
                ]);
            }
        }
    }
}
