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
            // It also skips most of the "People & Work" section (Our Teams,
            // Org Chart, Attendance, Projects, Payroll) -- day-to-day HR/PM
            // operations belong to Manager/HR/Finance, not the system
            // account. Employees is the exception: Super Admin keeps full
            // access (Create, Edit, Delete, List, Menu Access) so it can
            // manage the account roster directly. Only the "-menu"
            // permissions are excluded (these are pure sidebar-visibility
            // gates with no backend middleware of their own), so this only
            // hides the section; it doesn't take away any deeper permission
            // Super Admin might still need.
            // Approving/rejecting an Official Memo is reserved for Finance
            // Manager alone -- excluded from every other role's grant below,
            // even Super Admin/Manager, per the spec's explicit "hanya
            // Finance Manager" rule. The controller also hard-checks
            // hasRole('finance') so this holds even if permissions drift.
            $officialMemoApproveOnly = ['document-letter-approve'];

            // Meeting Note is deliberately scoped to Manager, Operational
            // Director, HR, and Finance Manager only -- not even Super
            // Admin, per spec. The controller also hard-checks hasAnyRole()
            // so this holds even if permissions drift.
            $meetingNotePermissions = [
                'meeting-note-menu',
                'meeting-note-list',
                'meeting-note-create',
                'meeting-note-edit',
                'meeting-note-delete',
                'meeting-note-pin',
            ];

            $superadmin->syncPermissions($this->permissionsAllExcept(array_merge($employeeSpecific, $officialMemoApproveOnly, $meetingNotePermissions, [
                'payslip-view',
                'task-list',
                'team-menu',
                'attendance-menu',
                'project-menu',
                'payroll-menu',
            ])));

            $manager->syncPermissions($this->permissionsAllExcept(array_merge($employeeSpecific, $officialMemoApproveOnly)));

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
                'document-letter-',
                'meeting-note-',
                'certificate-',
                'payslip-',
                'backup-',
                'announcement-',
                'asset-',
                'performance-review-',
                'staff-permission-',
            ], array_merge($employeeSpecific, $officialMemoApproveOnly)));

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
                'document-letter-',
                'meeting-note-',
                'certificate-',
                'payslip-',
                'backup-',
                'announcement-',
                'asset-',
                'performance-review-',
                'staff-permission-',
            ], array_merge($employeeSpecific, $officialMemoApproveOnly)));

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
                    'project-export',
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
                    // View-only: documents addressed to their team, or that
                    // they authored themselves -- no create/edit/delete/approve.
                    'document-letter-menu',
                    'document-letter-list',
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
                    // Finance Manager needs to view projects and pull the
                    // client-facing progress report, but not create/edit/
                    // delete them -- that stays with PM-facing roles.
                    'project-menu',
                    'project-list',
                    'project-export',
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
                    // Finance Manager is the sole approver of Official Memos,
                    // and can also author its own like any non-Staff role.
                    'document-letter-menu',
                    'document-letter-list',
                    'document-letter-create',
                    'document-letter-edit',
                    'document-letter-delete',
                    'document-letter-approve',
                    // Finance Manager is one of the 4 roles allowed into
                    // Meeting Note's shared repository.
                    'meeting-note-menu',
                    'meeting-note-list',
                    'meeting-note-create',
                    'meeting-note-edit',
                    'meeting-note-delete',
                    'meeting-note-pin',
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
