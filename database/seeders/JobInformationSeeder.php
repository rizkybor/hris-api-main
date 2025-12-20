<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JobInformationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Optimized for large datasets (500k+ records)
     */
    public function run(): void
    {
        $this->command->info('Checking employees without job information...');

        // Count employees without job information
        $totalEmployees = EmployeeProfile::whereDoesntHave('jobInformation')->count();

        if ($totalEmployees === 0) {
            $this->command->info('All employees already have job information.');

            return;
        }

        $this->command->info('Found '.number_format($totalEmployees).' employees without job information.');
        $this->command->info('Starting batch insert (this may take a while)...');

        // Get all available teams (cache in memory)
        $teams = Team::pluck('id')->toArray();

        // Job titles to randomly assign
        $jobTitles = [
            'Software Engineer', 'Senior Software Engineer', 'Frontend Developer',
            'Backend Developer', 'Full Stack Developer', 'DevOps Engineer',
            'QA Engineer', 'Product Manager', 'Project Manager',
            'UI/UX Designer', 'Data Analyst', 'Business Analyst',
            'Marketing Specialist', 'HR Specialist', 'Finance Officer',
            'Customer Support', 'System Administrator', 'Database Administrator',
            'Mobile Developer', 'Technical Lead',
        ];

        $employmentTypes = ['full-time', 'part-time', 'contract', 'intern'];
        $workLocations = ['office', 'remote', 'hybrid'];
        $skillLevels = ['junior', 'mid', 'senior', 'lead', 'principal'];

        $now = now();
        $processedCount = 0;
        $chunkSize = 1000; // Process 1000 records at a time

        $progressBar = $this->command->getOutput()->createProgressBar($totalEmployees);
        $progressBar->start();

        // Process in chunks for memory efficiency
        EmployeeProfile::whereDoesntHave('jobInformation')
            ->select('id') // Only select ID to reduce memory usage
            ->chunk($chunkSize, function ($employees) use (
                $teams,
                $jobTitles,
                $employmentTypes,
                $workLocations,
                $skillLevels,
                $now,
                &$processedCount,
                $progressBar
            ) {
                $insertData = [];

                foreach ($employees as $employee) {
                    // Randomly assign a team or null
                    $teamId = ! empty($teams) ? $teams[array_rand($teams)] : null;

                    $insertData[] = [
                        'employee_id' => $employee->id,
                        'job_title' => $jobTitles[array_rand($jobTitles)],
                        'team_id' => $teamId,
                        'years_experience' => rand(0, 15),
                        'status' => 'active',
                        'employment_type' => $employmentTypes[array_rand($employmentTypes)],
                        'work_location' => $workLocations[array_rand($workLocations)],
                        'start_date' => $now->copy()->subDays(rand(30, 1825))->format('Y-m-d'),
                        'monthly_salary' => rand(5000000, 25000000),
                        'skill_level' => $skillLevels[array_rand($skillLevels)],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Batch insert for better performance
                DB::table('job_information')->insert($insertData);

                $processedCount += count($employees);
                $progressBar->advance(count($employees));

                // Free memory
                unset($insertData);
            });

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('Successfully created job information for '.number_format($processedCount).' employees!');
    }
}
