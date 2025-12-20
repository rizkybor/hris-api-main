<?php

namespace App\Constants;

class CacheConstants
{
    /**
     * Cache TTL (Time To Live) in seconds
     */
    public const ONE_HOUR = 3600;

    public const ONE_DAY = 86400;

    /**
     * Bulk insert/update chunk sizes
     */
    public const PAYROLL_BULK_INSERT_CHUNK_SIZE = 500;

    public const DEFAULT_PAGINATION_SIZE = 50;

    /**
     * Cache key prefixes
     */
    public const CACHE_KEY_EMPLOYEE_STATISTICS = 'employee_statistics_';

    public const CACHE_KEY_DASHBOARD_STATISTICS = 'dashboard_statistics_';

    public const CACHE_KEY_PROJECT_STATISTICS = 'project_statistics_';

    public const CACHE_KEY_TEAM_STATISTICS = 'team_statistics_';

    public const CACHE_KEY_TEAM_CHART_DATA = 'team_chart_data_';

    public const CACHE_KEY_ATTENDANCE_STATISTICS = 'attendance_statistics_';

    public const CACHE_KEY_PAYROLL_STATISTICS = 'payroll_statistics_';

    public const CACHE_KEY_EMPLOYEE_TOTAL_COUNT = 'employee_total_count';
}
