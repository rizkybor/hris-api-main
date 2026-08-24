<?php

namespace App\Support;

/**
 * Code-defined catalog of dashboard widgets -- each key maps 1:1 to a Vue
 * component on the frontend (see hris-fe-main/src/dashboard/widgetRegistry.js).
 * Not a DB table: widgets aren't user-created content, they're a fixed set
 * shipped with the app, so a registry array is simpler than a migration +
 * CRUD for something that only ever changes when a developer adds a widget.
 *
 * `permission` gates whether a user can add this widget to their dashboard
 * at all (assignable per-role via the existing Roles & Permissions screen,
 * same as any other permission). Which widgets are actually on a given
 * user's dashboard, and in what order, is separately stored per-user in
 * DashboardWidgetLayout -- permission controls availability, the layout
 * table controls the individual's chosen selection/order among what's
 * available to them.
 */
class DashboardWidgetRegistry
{
    /**
     * `size` is the DEFAULT grid width for a widget the user has never
     * resized themselves -- "small" (1 of 3 columns), "medium" (2 of 3),
     * "large" (full row). Widgets whose content is itself a multi-card
     * grid (Key Metrics, My Overview, System Stats) default to "large" so
     * their internal cards aren't cramped into a third of the row.
     */
    public const WIDGETS = [
        ['key' => 'key_metrics', 'permission' => 'widget-key-metrics', 'size' => 'large'],
        ['key' => 'project_budget', 'permission' => 'widget-project-budget', 'size' => 'small'],
        ['key' => 'project_realized', 'permission' => 'widget-project-realized', 'size' => 'small'],
        ['key' => 'search_section', 'permission' => 'widget-search-section', 'size' => 'small'],
        ['key' => 'sticky_notes', 'permission' => 'widget-sticky-notes', 'size' => 'small'],
        ['key' => 'projects_at_risk', 'permission' => 'widget-projects-at-risk', 'size' => 'small'],
        ['key' => 'latest_employees', 'permission' => 'widget-latest-employees', 'size' => 'small'],
        ['key' => 'latest_teams', 'permission' => 'widget-latest-teams', 'size' => 'small'],
        ['key' => 'quick_links', 'permission' => 'widget-quick-links', 'size' => 'small'],
        ['key' => 'system_settings_links', 'permission' => 'widget-system-settings-links', 'size' => 'small'],
        ['key' => 'pending_leave_requests', 'permission' => 'widget-pending-leave-requests', 'size' => 'small'],
        ['key' => 'employee_statistics', 'permission' => 'widget-employee-statistics', 'size' => 'large'],
        ['key' => 'system_stats', 'permission' => 'widget-system-stats', 'size' => 'large'],
        ['key' => 'recent_activity', 'permission' => 'widget-recent-activity', 'size' => 'medium'],
    ];

    public const SIZES = ['small', 'medium', 'large'];

    /**
     * Per-role default widget order, matching each role's original
     * hardcoded Overview.vue layout exactly (before the widget permission/
     * drag-drop system existed) -- e.g. HR had Quick Access ahead of Latest
     * Employees while Manager had the opposite, so a single global default
     * order can't reproduce both simultaneously. Used only until a user
     * saves their own layout; after that, DashboardWidgetLayout wins.
     */
    public const ROLE_DEFAULT_ORDER = [
        'manager' => ['projects_at_risk', 'sticky_notes', 'key_metrics', 'project_budget', 'project_realized', 'latest_employees', 'latest_teams', 'quick_links'],
        'operational_director' => ['projects_at_risk', 'sticky_notes', 'key_metrics', 'project_budget', 'project_realized', 'latest_employees', 'latest_teams', 'quick_links'],
        'finance' => ['sticky_notes', 'key_metrics', 'project_budget', 'project_realized', 'quick_links'],
        'hr' => ['pending_leave_requests', 'sticky_notes', 'key_metrics', 'project_budget', 'quick_links', 'latest_employees'],
        'staff' => ['employee_statistics', 'search_section'],
        'superadmin' => ['system_stats', 'system_settings_links', 'recent_activity'],
    ];

    /**
     * Priority order for picking one default layout when a user holds more
     * than one role -- mirrors the priority the old per-role Dashboard.vue
     * branching used.
     */
    public const ROLE_PRIORITY = ['superadmin', 'manager', 'operational_director', 'finance', 'hr', 'staff'];

    public static function keys(): array
    {
        return array_column(self::WIDGETS, 'key');
    }

    /**
     * The default widget order for a user's highest-priority role, or the
     * registry's own declaration order as a last resort for a role with no
     * defined default (or a user with no matching role at all).
     */
    public static function defaultOrderFor(\Illuminate\Support\Collection $userRoleNames): array
    {
        foreach (self::ROLE_PRIORITY as $role) {
            if ($userRoleNames->contains($role)) {
                return self::ROLE_DEFAULT_ORDER[$role] ?? self::keys();
            }
        }

        return self::keys();
    }
}
