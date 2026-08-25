<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\BackupResource;
use App\Http\Resources\PaginateResource;
use App\Models\Backup;
use App\Services\DatabaseBackupService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;

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
            new Middleware(PermissionMiddleware::using(['backup-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['backup-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['backup-list']), only: ['download']),
            new Middleware(PermissionMiddleware::using(['backup-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['backup-restore']), only: ['restore']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $backups = Backup::with('creator')
                ->when($request->search, fn ($q) => $q->where('filename', 'like', '%'.$request->search.'%'))
                ->latest()
                ->paginate($request->row_per_page ?? 10);

            return ResponseHelper::jsonResponse(true, 'Backups Retrieved Successfully', PaginateResource::make($backups, BackupResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Generate a fresh full SQL backup and persist it to the backup history.
     */
    public function store(Request $request)
    {
        try {
            $backup = $this->backupService->createAndStore($request->user()->id);

            activity('Security')
                ->causedBy($request->user())
                ->withProperties(['filename' => $backup->filename])
                ->event('backup_created')
                ->log('generated a full database backup');

            return ResponseHelper::jsonResponse(true, 'Backup Created Successfully', new BackupResource($backup->load('creator')), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function download(Request $request, int $id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (! Storage::disk('local')->exists($backup->disk_path)) {
                return ResponseHelper::jsonResponse(false, 'Backup File Not Found', null, 404);
            }

            activity('Security')
                ->causedBy($request->user())
                ->withProperties(['filename' => $backup->filename])
                ->event('backup_downloaded')
                ->log('downloaded a database backup');

            return response()->download(Storage::disk('local')->path($backup->disk_path), $backup->filename, [
                'Content-Type' => 'application/gzip',
            ]);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Backup Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Overwrite the current database with a stored backup's snapshot. Highly
     * destructive and cannot be undone from the UI (the service takes a
     * fresh safety snapshot first, but restoring *that* would need to be
     * done manually the same way).
     */
    public function restore(Request $request, int $id)
    {
        try {
            $backup = Backup::findOrFail($id);

            $this->backupService->restoreFromBackup($backup, $request->user()->id);

            activity('Security')
                ->causedBy($request->user())
                ->withProperties(['filename' => $backup->filename])
                ->event('backup_restored')
                ->log('restored the database from a backup');

            return ResponseHelper::jsonResponse(true, 'Database Restored Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Backup Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $backup = Backup::findOrFail($id);

            if (Storage::disk('local')->exists($backup->disk_path)) {
                Storage::disk('local')->delete($backup->disk_path);
            }

            $backup->delete();

            return ResponseHelper::jsonResponse(true, 'Backup Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Backup Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
