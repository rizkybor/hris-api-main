<?php

namespace Database\Seeders;

use App\Models\BankInformation;
use App\Models\EmergencyContact;
use App\Models\EmployeeProfile;
use App\Models\JobInformation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting to create 1 employees...');
        $this->command->info('This will take several minutes. Please be patient...');
        $this->command->newLine();

        $totalEmployees = 1;
        $chunkSize = 500; // Reduced chunk size to 500 for better memory management
        $chunks = ceil($totalEmployees / $chunkSize);

        $progressBar = $this->command->getOutput()->createProgressBar($chunks);
        $progressBar->start();

        $startTime = microtime(true);

        for ($i = 0; $i < $chunks; $i++) {
            // Create each batch in its own transaction for better performance
            DB::transaction(function () use ($chunkSize) {
                $this->createEmployeeBatch($chunkSize);
            });
            $progressBar->advance();

            // Clear memory after each batch
            gc_collect_cycles();

            // Periodically show memory usage
            if (($i + 1) % 50 == 0) {
                $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2);
                $this->command->newLine();
                $this->command->info("Memory usage: {$memoryUsage} MB");
                $progressBar->display();
            }
        }

        $progressBar->finish();
        $this->command->newLine(2);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->command->info('✓ Successfully created ' . EmployeeProfile::count() . ' employees with complete data!');
        $this->command->info("✓ Time taken: {$duration} seconds");
        $this->command->info('✓ Average: ' . round($totalEmployees / $duration, 2) . ' employees/second');
    }

    /**
     * Create a batch of employees with related data
     */
    private function createEmployeeBatch(int $count): void
    {
        // Disable query log for better performance
        DB::connection()->disableQueryLog();

        $employees = EmployeeProfile::factory()
            ->count($count)
            ->create();

        // Create related data for all employees in this batch
        foreach ($employees as $employee) {
            $this->createRelatedData($employee);
        }

        // Clear the collection to free memory
        $employees = null;
        unset($employees);
    }

    /**
     * Create related data for employee (JobInformation, BankInformation, EmergencyContacts)
     */
    private function createRelatedData(EmployeeProfile $employee): void
    {
        // Create job information
        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->create();

        // Create bank information
        BankInformation::factory()
            ->forEmployee($employee)
            ->create();

        // Create 1-3 emergency contacts
        EmergencyContact::factory()
            ->count(rand(1, 3))
            ->forEmployee($employee)
            ->create();
    }
}
