<?php

namespace App\Constants;

class PermissionModules
{
    /**
     * Canonical module prefix => friendly label, mirroring the module keys
     * used in PermissionSeeder. Matching is done longest-prefix-first so a
     * permission like "company-finance-menu" resolves to "company-finance"
     * and never gets swallowed by a shorter, unrelated "company" bucket.
     */
    public const LABELS = [
        'dashboard' => 'Dashboard',
        'profile' => 'My Profile',
        'team' => 'Teams',
        'employee' => 'Employees',
        'project' => 'Projects',
        'task' => 'Tasks',
        'attendance' => 'Attendance',
        'leave-request' => 'Leave Requests',
        'payroll' => 'Payroll',
        'company-about' => 'Company About',
        'credential-account' => 'Credential Accounts',
        'company-finance' => 'Operational Cost',
        'fixed-cost' => 'Fixed Costs',
        'infrastructure-tool' => 'Infrastructure Tools',
        'sdm-resource' => 'SDM Resources',
        'files-company' => 'Document Files',
        'report' => 'Reports',
        'role' => 'Roles & Permissions',
        'purchase-order' => 'Purchase Orders',
        'invoice' => 'Invoices',
        'payment-receipt' => 'Payment Receipts',
        'letter' => 'Document Letters',
        'vendors' => 'Vendors',
        'payslip' => 'Payslips',
        'backup' => 'Database Backup',
    ];

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
}
