<?php

namespace App\Constants;

class PermissionModules
{
    /**
     * Canonical module prefix => friendly label, mirroring the module keys
     * used in PermissionSeeder. Matching is done longest-prefix-first so a
     * permission like "company-finance-menu" resolves to "company-finance"
     * and never gets swallowed by a shorter, unrelated "company" bucket.
     *
     * Keep this in sync with every top-level key in PermissionSeeder -- a
     * missing entry doesn't just show an ugly fallback label, it can make
     * resolve() wrongly match a shorter, unrelated prefix instead (e.g.
     * "project-expense-menu" silently merging into "Projects" because
     * "project" was the only registered prefix that was a string-prefix of
     * it). Add the new key here in the same commit as a new module.
     */
    public const LABELS = [
        'dashboard' => 'Dashboard',
        'widget' => 'Dashboard Widgets',
        'profile' => 'My Profile',
        'team' => 'Teams',
        'employee' => 'Employees',
        'performance-review' => 'Performance Reviews',
        'project' => 'Projects',
        'project-expense' => 'Project Cash Ledger',
        'project-calculator' => 'Project Calculator',
        'task' => 'Tasks',
        'staff-task' => 'Staff Tasks',
        'staff-raport' => 'Staff Raport',
        'attendance' => 'Attendance',
        'attendance-setting' => 'Attendance Settings',
        'leave-request' => 'Leave Requests',
        'payroll' => 'Payroll',
        'payslip' => 'Payslips',
        'company-about' => 'Company About',
        'company-finance' => 'Operational Cost',
        'company-cash-book' => 'Company Cash Book',
        'fixed-cost' => 'Fixed Costs',
        'infrastructure-tool' => 'Infrastructure Tools',
        'sdm-resource' => 'SDM Resources',
        'sdm-field' => 'SDM Fields',
        'credential-account' => 'Credential Accounts',
        'files-company' => 'Document Files',
        'report' => 'Reports',
        'role' => 'Roles & Permissions',
        'option' => 'Dropdown Options',
        'staff-permission' => 'Staff Account Permissions',
        'purchase-order' => 'Purchase Orders',
        'invoice' => 'Invoices',
        'payment-receipt' => 'Payment Receipts',
        'letter' => 'Letters',
        'document-letter' => 'Official Memos',
        'meeting-note' => 'Meeting Notes',
        'certificate' => 'Certificates',
        'clients' => 'Clients',
        'subscription' => 'Subscriptions',
        'backup' => 'Database Backup',
        'history' => 'History',
        'announcement' => 'Announcements',
        'asset' => 'Company Assets',
        'greeting' => 'Calendar Greetings',
        'analytics' => 'Analytics',
    ];

    /**
     * Module key => the sidebar section it lives under, so the Roles &
     * Permissions matrix can be grouped the same way the app's own
     * navigation is grouped instead of one long A-Z list of ~40 modules.
     * A handful of modules span two sidebar spots (e.g. "team" backs both
     * My Team under My Workspace and Our Teams/Org Chart under
     * Administration); those are filed under whichever placement is the
     * more significant, CRUD-heavy one for role configuration purposes.
     */
    public const SECTIONS = [
        'dashboard' => 'General',
        'widget' => 'General',
        'payslip' => 'General',

        'profile' => 'My Workspace',
        'task' => 'My Workspace',
        'staff-task' => 'My Workspace',
        'project' => 'My Workspace',
        'attendance' => 'My Workspace',
        'leave-request' => 'My Workspace',

        'team' => 'Administration',
        'employee' => 'Administration',
        'performance-review' => 'Administration',
        'clients' => 'Administration',
        'subscription' => 'Administration',
        'purchase-order' => 'Administration',
        'invoice' => 'Administration',
        'payment-receipt' => 'Administration',
        'letter' => 'Administration',
        'document-letter' => 'Administration',
        'meeting-note' => 'Administration',
        'certificate' => 'Administration',

        'company-finance' => 'Finance',
        'company-cash-book' => 'Finance',
        'fixed-cost' => 'Finance',
        'infrastructure-tool' => 'Finance',
        'sdm-resource' => 'Finance',
        'project-expense' => 'Finance',
        'payroll' => 'Finance',
        'report' => 'Finance',
        'staff-raport' => 'Finance',

        'project-calculator' => 'Tools',

        'analytics' => 'Insights',
        'history' => 'Insights',

        'company-about' => 'Company',
        'asset' => 'Company',
        'files-company' => 'Company',
        'credential-account' => 'Company',
        'announcement' => 'Company',
        'attendance-setting' => 'Company',
        'sdm-field' => 'Company',
        'role' => 'Company',
        'option' => 'Company',
        'staff-permission' => 'Company',
        'backup' => 'Company',
        'greeting' => 'Company',
    ];

    /**
     * Sidebar order top-to-bottom, so sections in the permission matrix
     * read in the same order a role-assigner already sees in the app.
     */
    public const SECTION_ORDER = ['General', 'My Workspace', 'Administration', 'Finance', 'Tools', 'Insights', 'Company'];

    /**
     * Resolve the module key for a permission name (e.g. "company-finance-menu"
     * => "company-finance"), matching the longest known prefix first.
     */
    public static function resolve(string $permissionName): string
    {
        $prefixes = array_keys(self::LABELS);
        usort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            if ($permissionName === $prefix || str_starts_with($permissionName, $prefix.'-')) {
                return $prefix;
            }
        }

        // Fallback: first hyphen segment, for any future permission that
        // hasn't been registered above yet.
        return explode('-', $permissionName)[0];
    }

    public static function label(string $moduleKey): string
    {
        return self::LABELS[$moduleKey] ?? ucwords(str_replace('-', ' ', $moduleKey));
    }

    public static function section(string $moduleKey): string
    {
        return self::SECTIONS[$moduleKey] ?? 'Other';
    }

    public static function sectionOrderIndex(string $section): int
    {
        $index = array_search($section, self::SECTION_ORDER, true);

        return $index === false ? count(self::SECTION_ORDER) : $index;
    }
}
