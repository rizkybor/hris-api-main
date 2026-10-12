<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ServerStorageController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['server-storage-menu|server-storage-view']), only: ['index']),
        ];
    }

    /**
     * Disk usage of the VPS the API runs on, plus how much of it this app
     * accounts for. The disk figures are for the volume holding the app
     * itself -- on the VPS that's the root volume shared with the database
     * and logs, so it's the number that actually runs out.
     */
    public function index()
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if ($total === false || $free === false || $total <= 0) {
            return ResponseHelper::jsonResponse(false, 'Unable to read server disk usage', null, 500);
        }

        $used = $total - $free;

        return ResponseHelper::jsonResponse(true, 'Server Storage Retrieved Successfully', [
            'disk' => [
                'total_bytes' => (int) $total,
                'used_bytes' => (int) $used,
                'free_bytes' => (int) $free,
                'used_percent' => round($used / $total * 100, 1),
            ],
            // Either breakdown entry is null when it can't be measured
            // (e.g. no access to information_schema), rather than failing
            // the whole response over a secondary figure.
            'breakdown' => [
                'database_bytes' => $this->databaseSize(),
                'app_storage_bytes' => $this->directorySize(storage_path()),
            ],
            'checked_at' => now()->toIso8601String(),
        ], 200);
    }

    private function databaseSize(): ?int
    {
        try {
            $size = DB::table('information_schema.tables')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->sum(DB::raw('data_length + index_length'));

            return (int) $size;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function directorySize(string $path): ?int
    {
        try {
            $size = 0;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }

            return $size;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
