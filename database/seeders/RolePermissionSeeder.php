<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $manager = Role::firstOrCreate(['name' => 'manager']);
            $hr = Role::firstOrCreate(['name' => 'hr']);
            $employee = Role::firstOrCreate(['name' => 'employee']);
            $finance = Role::firstOrCreate(['name' => 'finance']);

            $employeeSpecific = [
                'attendance-my-attendances',
                'attendance-last-attendance',
                'attendance-check-in',
                'attendance-check-out',
                'leave-request-my-requests',
                'profile-menu',
                'team-view',
            ];

            $manager->syncPermissions($this->permissionsAllExcept($employeeSpecific));

            $hr->syncPermissions($this->permissionsByPrefixes([
                'dashboard-',
                'team-',
                'employee-',
                'project-',
                'task-',
                'attendance-',
                'leave-request-',
                'credential-account-',
                'files-company-',
                'company-about-',
                'sdm-resource-',
                'vendors-',
                'vendors-attachment',
                'vendors-task-list',
                'vendors-task-scope',
                'vendors-task-payment',
                'vendors-task-pivot',
            ], $employeeSpecific));

            $employee->syncPermissions(
                Permission::whereIn('name', [
                    'dashboard-menu',
                    'dashboard-view',
                    'profile-menu',
                    'profile-view',
                    'employee-list',
                    'team-view',
                    'attendance-my-attendances',
                    'attendance-check-in',
                    'attendance-check-out',
                    'attendance-last-attendance',
                    'leave-request-menu',
                    'leave-request-create',
                    'leave-request-my-requests',
                    'project-menu',
                    'project-list',
                    'task-menu',
                    'task-create',
                    'task-list',
                    'task-edit',
                    'company-about-menu',
                ])->get()
            );

            $finance->syncPermissions(
                Permission::whereIn('name', [
                    'dashboard-menu',
                    'dashboard-view',
                    'profile-menu',
                    'profile-view',
                    'employee-menu',
                    'employee-list',
                    'attendance-menu',
                    'attendance-list',
                    'leave-request-menu',
                    'leave-request-list',
                    'payroll-menu',
                    'payroll-list',
                    'payroll-create',
                    'payroll-edit',
                    'payroll-delete',
                    'payroll-process',
                    'payroll-statistics',
                    'credential-account-menu',
                    'credential-account-list',
                    'credential-account-create',
                    'credential-account-edit',
                    'credential-account-delete',
                    'files-company-menu',
                    'files-company-list',
                    'files-company-create',
                    'files-company-edit',
                    'files-company-delete',
                    'company-about-menu',
                    'company-about-create',
                    'company-about-edit',
                    'company-about-delete',
                    'company-finance-menu',
                    'company-finance-create',
                    'company-finance-edit',
                    'company-finance-delete',
                    'company-finance-statistic',
                    'fixed-cost-list',
                    'fixed-cost-create',
                    'fixed-cost-edit',
                    'fixed-cost-delete',
                    'infrastructure-tool-list',
                    'infrastructure-tool-create',
                    'infrastructure-tool-edit',
                    'infrastructure-tool-delete',
                    'sdm-resource-list',
                    'sdm-resource-create',
                    'sdm-resource-edit',
                    'sdm-resource-delete',
                    'vendors-menu',
                    'vendors-list',
                    'vendors-create',
                    'vendors-edit',
                    'vendors-delete',
                    'vendors-attachment-list',
                    'vendors-attachment-create',
                    'vendors-attachment-edit',
                    'vendors-attachment-delete',
                    'vendors-task-list',
                    'vendors-task-list-create',
                    'vendors-task-list-edit',
                    'vendors-task-list-delete',
                    'vendors-task-scope-list',
                    'vendors-task-scope-create',
                    'vendors-task-scope-edit',
                    'vendors-task-scope-delete',
                    'vendors-task-payment-list',
                    'vendors-task-payment-create',
                    'vendors-task-payment-edit',
                    'vendors-task-payment-delete',
                    'vendors-task-pivot-list',
                    'vendors-task-pivot-create',
                    'vendors-task-pivot-edit',
                    'vendors-task-pivot-delete',
                ])->get()
            );
        });
    }

    private function permissionsAllExcept(array $except): Collection
    {
        return Permission::whereNotIn('name', $except)->get();
    }

    private function permissionsByPrefixes(array $prefixes, array $except = []): Collection
    {
        return Permission::where(function ($q) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $q->orWhere('name', 'like', $prefix . '%');
            }
        })->when(! empty($except), function ($q) use ($except) {
            $q->whereNotIn('name', $except);
        })->get();
    }
}
