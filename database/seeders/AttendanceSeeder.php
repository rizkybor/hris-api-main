<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\EmployeeProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * NOTE: Simplified to generate minimal data for payroll testing.
     * Only processes first 5,000 employees instead of all 500k.
     */
    public function run(): void
    {
        DB::connection()->disableQueryLog();

        $employeeLimit = 500;

        $this->command->info("Starting attendance seeding for {$employeeLimit} employees (current month)...");
        $this->command->newLine();

        $startTime = microtime(true);
        $totalRecords = 0;

        // Get work days for current month
        $workDays = $this->getWorkDaysInMonth();
        $this->command->info('Work days in month: '.count($workDays));

        // Get limited set of employees for payroll testing
        $employees = EmployeeProfile::limit($employeeLimit)->get();

        $progressBar = $this->command->getOutput()->createProgressBar($employees->count());
        $progressBar->start();

        $attendanceBatch = [];

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // Random attendance status (60% present, 15% late, 10% sick, 10% permission, 5% absent)
                $rand = rand(1, 100);
                if ($rand <= 60) {
                    $status = 'present';
                } elseif ($rand <= 75) {
                    $status = 'late';
                } elseif ($rand <= 85) {
                    $status = 'sick';
                } elseif ($rand <= 95) {
                    $status = 'permission';
                } else {
                    $status = 'absent';
                }

                // Generate check-in/check-out times for present and late
                $checkIn = null;
                $checkOut = null;

                if ($status === 'present') {
                    $checkIn = $date.' '.sprintf('%02d:%02d:00', 8, rand(0, 15));
                    $checkOut = $date.' '.sprintf('%02d:%02d:00', 17, rand(0, 30));
                } elseif ($status === 'late') {
                    $checkIn = $date.' '.sprintf('%02d:%02d:00', rand(8, 9), rand(16, 59));
                    $checkOut = $date.' '.sprintf('%02d:%02d:00', 17, rand(0, 30));
                }

                $attendanceBatch[] = [
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => $status,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Bulk insert every 1000 records to avoid memory issues
                if (count($attendanceBatch) >= 1000) {
                    DB::table('attendances')->insert($attendanceBatch);
                    $totalRecords += count($attendanceBatch);
                    $attendanceBatch = [];
                }
            }

            $progressBar->advance();
        }

        // Insert remaining records
        if (! empty($attendanceBatch)) {
            DB::table('attendances')->insert($attendanceBatch);
            $totalRecords += count($attendanceBatch);
        }

        $progressBar->finish();
        $this->command->newLine(2);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->command->info("✓ Successfully created {$totalRecords} attendance records!");
        $this->command->info("✓ Employees: {$employees->count()}");
        $this->command->info('✓ Work days: '.count($workDays));
        $this->command->info("✓ Time taken: {$duration} seconds");
        $this->command->info('✓ Average: '.round($totalRecords / $duration, 2).' records/second');
    }

    /**
     * Get all work days (Monday-Saturday, excluding Sunday) in current month
     */
    private function getWorkDaysInMonth(): array
    {
        $workDays = [];
        $month = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $currentDate = $month->copy();

        while ($currentDate->lte($endOfMonth)) {
            // Include Monday-Saturday (exclude Sunday only)
            if (! $currentDate->isSunday()) {
                $workDays[] = $currentDate->format('Y-m-d');
            }
            $currentDate->addDay();
        }

        return $workDays;
    }
}
