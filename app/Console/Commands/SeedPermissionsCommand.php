<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:permissions {--fresh : Run fresh migrations before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed permissions and role permissions for the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting permission seeding process...');

        if ($this->option('fresh')) {
            $this->info('Running fresh migrations...');
            $this->call('migrate:fresh');
        }

        $this->info('Seeding permissions...');
        $this->call('db:seed', ['--class' => 'PermissionSeeder']);

        $this->info('Seeding role permissions...');
        $this->call('db:seed', ['--class' => 'RolePermissionSeeder']);

        $this->info('✅ Permission seeding completed successfully!');
    }
}
