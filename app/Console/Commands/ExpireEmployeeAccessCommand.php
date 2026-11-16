<?php

namespace App\Console\Commands;

use App\Models\JobInformation;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Switches off the login of employees whose contract or probation end date
 * has just passed (the end date is their last day). Only dates in the last
 * EXPIRY_WINDOW_DAYS days count -- an old, never-cleared probation date of
 * a permanent employee doesn't lock them out -- and a date is processed
 * once, so a manual reactivation afterwards sticks.
 */
class ExpireEmployeeAccessCommand extends Command
{
    private const EXPIRY_WINDOW_DAYS = 7;

    protected $signature = 'employees:expire-access {--dry-run : List who would be deactivated without changing anything}';

    protected $description = 'Deactivate the login of employees whose contract or probation has ended';

    public function handle(): int
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $from = $today->copy()->subDays(self::EXPIRY_WINDOW_DAYS)->toDateString();
        $to = $today->copy()->subDay()->toDateString();

        $jobs = JobInformation::with('employee.user')
            ->whereIn('status', ['active', 'on_leave']) // still employed
            // whereDate: compares the date part only, whatever the column stores.
            ->where(fn ($q) => $q
                ->where(fn ($c) => $c->whereDate('contract_end_date', '>=', $from)->whereDate('contract_end_date', '<=', $to))
                ->orWhere(fn ($p) => $p->whereDate('probation_end_date', '>=', $from)->whereDate('probation_end_date', '<=', $to)))
            ->get();

        $done = 0;
        foreach ($jobs as $job) {
            $user = $job->employee?->user;
            if (! $user || $user->isProtected()) {
                continue;
            }

            // The earliest end date that has just passed decides.
            $ends = collect(['contract' => $job->contract_end_date, 'probation' => $job->probation_end_date])
                ->filter(fn ($date) => $date && $date->toDateString() >= $from && $date->toDateString() <= $to)
                ->sort();
            $reason = $ends->keys()->first();
            $endedOn = $ends->first()->toDateString();

            if ($user->access_ended_on?->toDateString() === $endedOn) {
                continue; // already processed (and possibly reactivated by hand)
            }

            $this->line(sprintf('%s %s (%s ended %s)', $this->option('dry-run') ? '[dry-run]' : 'Deactivated', $user->name, $reason, $endedOn));
            $done++;
            if ($this->option('dry-run')) {
                continue;
            }

            $user->tokens()->delete();
            $user->forceFill(['is_active' => false, 'access_ended_on' => $endedOn, 'access_end_reason' => $reason])->save();
        }

        $this->info(($this->option('dry-run') ? 'Would deactivate' : 'Deactivated').": {$done}");

        return self::SUCCESS;
    }
}
