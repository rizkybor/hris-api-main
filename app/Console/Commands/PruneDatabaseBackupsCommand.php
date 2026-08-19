<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDatabaseBackupsCommand extends Command
{
    protected $signature = 'backup:prune {--keep=14 : Number of most recent backups to retain}';

    protected $description = 'Delete old database backups beyond the configured retention count';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');

        $keptIds = Backup::latest()->take($keep)->pluck('id');
        $stale = Backup::whereNotIn('id', $keptIds)->get();

        if ($stale->isEmpty()) {
            $this->info('No backups to prune.');

            return self::SUCCESS;
        }

        foreach ($stale as $backup) {
            if (Storage::disk('local')->exists($backup->disk_path)) {
                Storage::disk('local')->delete($backup->disk_path);
            }

            $backup->delete();
        }

        $this->info("Pruned {$stale->count()} backup(s), keeping the {$keep} most recent.");

        return self::SUCCESS;
    }
}
