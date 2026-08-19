<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class RunDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Generate a full, gzip-compressed database backup and record it in the backup history';

    public function handle(DatabaseBackupService $backupService): int
    {
        $this->info('Starting database backup...');

        try {
            $backup = $backupService->createAndStore(null, isAutomatic: true);
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        activity('Security')
            ->withProperties(['filename' => $backup->filename])
            ->event('backup_created')
            ->log('generated a scheduled database backup');

        $this->info("Backup created: {$backup->filename} ({$backup->size_bytes} bytes)");

        return self::SUCCESS;
    }
}
