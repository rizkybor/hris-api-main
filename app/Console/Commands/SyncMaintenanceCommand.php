<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class SyncMaintenanceCommand extends Command
{
    protected $signature = 'maintenance:sync';

    protected $description = 'Close finished maintenance months, open the current one and expire old development balance';

    public function handle(MaintenanceService $service): int
    {
        $this->info('Synced '.$service->syncAll().' maintenance contract(s).');

        return self::SUCCESS;
    }
}
