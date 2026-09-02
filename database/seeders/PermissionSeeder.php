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
            'export',
        ],

        // Cash ledger (debit/credit) recorded against a project's budget --
        // 'list' gates viewing it (open to anyone who can view the
        // project), while create/edit/delete are additionally restricted
        // in ProjectCashTransactionController to that project's own
        // Project Leader (or manager/superadmin), since "leader" is
        // per-project and can't be expressed as a role permission alone.
        'project-expense' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        // Company-wide cash book -- real debit/credit transactions as they
        // actually happen (distinct from Company Finance, which tracks
        // planned/budgeted costs). 'create' also gates setting the ledger's
        // opening balance. Auto-synced entries mirrored in from a
        // project's own cash ledger can only be edited/deleted from that
        // project (see CompanyCashTransactionController), regardless of
        // this permission.
        'company-cash-book' => [
            'menu',
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

        'payslip' => [
            'view',
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

        'project-calculator' => [
            'menu',
            'create',
            'edit',
            'delete',
            'settings',
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

        'sdm-field' => [
            'menu',
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

        'report' => [
            'menu',
            'view',
            'export',
        ],

        'role' => [
            'menu',
            'view',
            'create',
            'edit',
            'delete',
        ],

        'option' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'staff-permission' => [
            'menu',
            'list',
            'edit',
        ],

        'purchase-order' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'invoice' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'payment-receipt' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'letter' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'document-letter' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'approve',
        ],

        'meeting-note' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'pin',
        ],

        'staff-task' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'staff-raport' => [
            'menu',
            'list',
            'export',
        ],

        'attendance-setting' => [
            'edit',
        ],

        'certificate' => [
            'menu',
            'list',
            'create',
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
            'evaluation-list',
            'evaluation-create',
            'evaluation-delete',
        ],

        'backup' => [
            'list',
            'create',
            'delete',
            'restore',
        ],

        'history' => [
            'menu',
            'view',
        ],

        'announcement' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'asset' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'assign',
            'my-assets',
            'maintenance-list',
            'maintenance-create',
            'maintenance-delete',
        ],

        'performance-review' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'my-reviews',
            'acknowledge',
        ],

        // Configuration for the Calendar Greetings shown on the dashboard
        // welcome banner (national holidays, birthdays, meeting reminders,
        // etc). Viewing today's greeting itself needs no permission -- see
        // GET /greetings/today in routes/api.php -- these only gate the
        // Settings screen that manages the list.
        'greeting' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        // Gates whether a user can add a given widget to their own
        // dashboard at all (see App\Support\DashboardWidgetRegistry).
        // Which widgets are actually on their dashboard, and in what
        // order, is a separate per-user preference (dashboard_widget_layouts).
        'widget' => [
            'key-metrics',
            'project-budget',
            'project-realized',
            'search-section',
            'sticky-notes',
            'projects-at-risk',
            'latest-employees',
            'latest-teams',
            'quick-links',
            'system-settings-links',
            'pending-leave-requests',
            'employee-statistics',
            'system-stats',
            'recent-activity',
        ],

        // Analytics monitoring page (embedded PostHog / GA4 / Search
        // Console reports, grouped by category) -- Manager, Finance
        // Manager, Operational Director, and Super Admin only.
        'analytics' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
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
