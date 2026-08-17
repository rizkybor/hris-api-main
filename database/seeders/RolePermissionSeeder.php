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
            $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
            $manager = Role::firstOrCreate(['name' => 'manager']);
            $hr = Role::firstOrCreate(['name' => 'hr']);
            $operationalDirector = Role::firstOrCreate(['name' => 'operational_director']);
            $employee = Role::firstOrCreate(['name' => 'staff']);
            $finance = Role::firstOrCreate(['name' => 'finance']);

            $employeeSpecific = [
                'attendance-my-attendances',
                'attendance-last-attendance',
                'attendance-check-in',
                'attendance-check-out',
                'leave-request-my-requests',
                'profile-menu',
                'team-view',
                'asset-my-assets',
                'performance-review-my-reviews',
                'performance-review-acknowledge',
            ];

            // Super Admin gets every permission except the full "My
            // Workspace" self-service set (My Profile, My Team, My
            // Attendance, Clock In/Out, My Tasks, My Payslips) -- it's a
            // system account, not a staff member with their own workspace
            // to view. Excluding "task-list" also removes viewing/posting
            // comments on project tasks (the same permission gates both,
            // and Super Admin doesn't need that either).
            //
            // It also skips the "People & Work" section (Employees, Our
            // Teams, Org Chart, Attendance, Projects, Payroll) -- day-to-day
            // HR/PM operations belong to Manager/HR/Finance, not the system
            // account. Only the "-menu" permissions are excluded (these are
            // pure sidebar-visibility gates with no backend middleware of
            // their own), so this only hides the section; it doesn't take
            // away any deeper permission Super Admin might still need.
            $superadmin->syncPermissions($this->permissionsAllExcept(array_merge($employeeSpecific, [
                'payslip-view',
                'task-list',
                'employee-menu',
                'team-menu',
                'attendance-menu',
                'project-menu',
                'payroll-menu',
            ])));

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
                'report-',
                'purchase-order-',
                'invoice-',
                'payment-receipt-',
                'letter-',
                'certificate-',
                'payslip-',
                'backup-',
                'announcement-',
                'asset-',
                'performance-review-',
                'staff-permission-',
            ], $employeeSpecific));

            // Operational Director shares HR's operational scope -- it's a
            // distinct role (Aldi's account), not a rename of HR, so both
            // remain independently selectable and permissioned.
            $operationalDirector->syncPermissions($this->permissionsByPrefixes([
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
                'report-',
                'purchase-order-',
                'invoice-',
                'payment-receipt-',
                'letter-',
                'certificate-',
                'payslip-',
                'backup-',
                'announcement-',
                'asset-',
                'performance-review-',
                'staff-permission-',
            ], $employeeSpecific));

            $employee->syncPermissions(
                Permission::whereIn('name', [
                    'dashboard-menu',
                    'dashboard-view',
                    'profile-menu',
                    'profile-view',
                    'employee-list',
                    'team-view',
                    'payslip-view',
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
                    'announcement-menu',
                    'announcement-list',
                    'asset-my-assets',
                    'performance-review-my-reviews',
                    'performance-review-acknowledge',
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
                    'payslip-view',
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
                    'project-calculator-menu',
                    'project-calculator-create',
                    'project-calculator-edit',
                    'project-calculator-delete',
                    'project-calculator-settings',
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
                    'report-menu',
                    'report-view',
                    'report-export',
                    'purchase-order-menu',
                    'purchase-order-list',
                    'purchase-order-create',
                    'purchase-order-edit',
                    'purchase-order-delete',
                    'invoice-menu',
                    'invoice-list',
                    'invoice-create',
                    'invoice-edit',
                    'invoice-delete',
                    'payment-receipt-menu',
                    'payment-receipt-list',
                    'payment-receipt-create',
                    'payment-receipt-edit',
                    'payment-receipt-delete',
                    'letter-menu',
                    'letter-list',
                    'letter-create',
                    'letter-edit',
                    'letter-delete',
                    'certificate-menu',
                    'certificate-list',
                    'certificate-create',
                    'certificate-delete',
                    'announcement-menu',
                    'announcement-list',
                    'asset-menu',
                    'asset-list',
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
