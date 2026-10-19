<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ServerStorageController extends Controller implements HasMiddleware
{
    private const CACHE_KEY = 'server-storage';

    private const CACHE_MINUTES = 10;

    /**
     * Folders inside base_path() reported as their own category, checked
     * in order (first prefix match wins). Anything under base_path() that
     * matches none of these counts as "application".
     */
    private const APP_CATEGORIES = [
        'backups' => ['label' => 'Database Backups', 'paths' => ['storage/app/private/backups', 'storage/app/backups']],
        'uploads' => ['label' => 'Uploaded Files', 'paths' => ['storage/app']],
        'logs' => ['label' => 'Logs', 'paths' => ['storage/logs']],
        'search_index' => ['label' => 'Search Index', 'paths' => ['storage/tntsearch']],
        'cache' => ['label' => 'Cache & Sessions', 'paths' => ['storage/framework']],
        'dependencies' => ['label' => 'Dependencies', 'paths' => ['vendor', 'node_modules']],
    ];

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['server-storage-menu|server-storage-view']), only: ['index']),
        ];
    }

    /**
     * Disk usage of the VPS the API runs on, broken down by what's using
     * it. The disk figures are for the volume holding the app itself -- on
     * the VPS that's the root volume shared with the database and logs, so
     * it's the number that actually runs out.
     *
     * Walking every file under the app (vendor alone is tens of thousands)
     * is too slow to redo on every page view, so the result is cached for a
     * few minutes; ?refresh=1 forces a fresh scan.
     */
    public function index(Request $request)
    {
        if ($request->boolean('refresh')) {
            Cache::forget(self::CACHE_KEY);
        }

        $data = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), fn () => $this->scan());

        if ($data === null) {
            return ResponseHelper::jsonResponse(false, 'Unable to read server disk usage', null, 500);
        }

        // Lets the page pair this server with its monitoring agent's report
        // (the agent reports the same hostname) for RAM, CPU and websites.
        $data['hostname'] = gethostname() ?: null;

        return ResponseHelper::jsonResponse(true, 'Server Storage Retrieved Successfully', $data, 200);
    }

    private function scan(): ?array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $used = $total - $free;

        $database = $this->databaseUsage();
        $appSizes = $this->appUsage();

        $categories = [];
        if ($database !== null) {
            $categories[] = ['key' => 'database', 'label' => 'Database', 'bytes' => $database['bytes']];
        }
        foreach (self::APP_CATEGORIES as $key => $category) {
            $categories[] = ['key' => $key, 'label' => $category['label'], 'bytes' => $appSizes[$key]];
        }
        $categories[] = ['key' => 'application', 'label' => 'Application Code', 'bytes' => $appSizes['application']];

        // Everything on the disk this app can't attribute to itself: the
        // OS, other sites/apps on the VPS, MySQL's own logs, etc. -- those
        // live in folders the web user generally can't read, so they're
        // reported as one remainder rather than guessed at.
        $known = array_sum(array_column($categories, 'bytes'));
        $categories[] = ['key' => 'system', 'label' => 'System & Other', 'bytes' => max(0, (int) $used - $known)];

        return [
            'disk' => [
                'total_bytes' => (int) $total,
                'used_bytes' => (int) $used,
                'free_bytes' => (int) $free,
                'used_percent' => round($used / $total * 100, 1),
            ],
            'categories' => $categories,
            'largest_tables' => $database['largest_tables'] ?? [],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Size of this app's database as MySQL reports it (data + indexes), and
     * its biggest tables. null when information_schema can't be queried.
     */
    private function databaseUsage(): ?array
    {
        try {
            $tables = DB::table('information_schema.tables')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->select('table_name as name', DB::raw('COALESCE(data_length, 0) + COALESCE(index_length, 0) as bytes'))
                ->orderByDesc('bytes')
                ->get();

            return [
                'bytes' => (int) $tables->sum('bytes'),
                'largest_tables' => $tables->take(5)->map(fn ($t) => ['name' => $t->name, 'bytes' => (int) $t->bytes])->values()->all(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * One pass over every file under base_path(), bucketing each into
     * APP_CATEGORIES by path. Unreadable folders are skipped rather than
     * aborting the scan, and symlinked folders aren't followed (public/storage
     * points back into storage/app, which would otherwise count twice).
     */
    private function appUsage(): array
    {
        $root = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $sizes = array_fill_keys([...array_keys(self::APP_CATEGORIES), 'application'], 0);

        $prefixes = [];
        foreach (self::APP_CATEGORIES as $key => $category) {
            foreach ($category['paths'] as $path) {
                $prefixes[] = [$path.DIRECTORY_SEPARATOR, $key];
            }
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($files as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root));
            $bucket = 'application';
            foreach ($prefixes as [$prefix, $key]) {
                if (str_starts_with($relative, $prefix)) {
                    $bucket = $key;
                    break;
                }
            }

            $sizes[$bucket] += $file->getSize();
        }

        return $sizes;
    }
}
