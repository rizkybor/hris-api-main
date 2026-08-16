<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller implements HasMiddleware
{
    private DatabaseBackupService $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['backup-create']), only: ['download']),
        ];
    }

    /**
     * Stream a full SQL backup of every table in the database.
     */
    public function download(): StreamedResponse
    {
        $filename = 'hris-backup-'.now()->format('Y-m-d_His').'.sql';

        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            $this->backupService->streamDump($handle);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/sql');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
