<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionDueReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendSubscriptionDueRemindersCommand extends Command
{
    protected $signature = 'subscriptions:send-due-reminders
                            {--days= : Days before the due date to remind (default: services.subscription_reminder.days_before)}
                            {--dry-run : List what would be sent without sending or marking anything}';

    protected $description = 'Email the company inbox about active subscriptions whose next due date is coming up';

    /**
     * Picks every active subscription due between today and today + N days
     * that hasn't been reminded for its current due date yet -- not only
     * those due exactly N days from now, so a day the scheduler didn't run,
     * or a subscription created with less than N days to go, still gets its
     * one reminder instead of silently never getting one.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('services.subscription_reminder.days_before', 30));
        $recipient = config('services.subscription_reminder.recipient');
        $dryRun = (bool) $this->option('dry-run');

        // Dates are compared in Jakarta time: the app runs in UTC, and the
        // team's "today" (and a due date's H-30) is the local calendar day.
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $until = $today->copy()->addDays($days);

        $subscriptions = Subscription::with(['client', 'project', 'services'])
            ->where('status', 'active')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '>=', $today->toDateString())
            ->whereDate('next_due_date', '<=', $until->toDateString())
            // Compared as DATE() so a stored time part (some drivers write
            // a date cast as "Y-m-d 00:00:00") can't make the same day look
            // different and re-send the reminder every run.
            ->where(fn ($q) => $q->whereNull('due_reminder_sent_for')->orWhereRaw('DATE(due_reminder_sent_for) <> DATE(next_due_date)'))
            ->orderBy('next_due_date')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info("No subscriptions due within {$days} days need a reminder.");

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            $dueDate = Carbon::parse($subscription->next_due_date->toDateString(), 'Asia/Jakarta');
            $daysRemaining = (int) $today->diffInDays($dueDate);
            $label = "#{$subscription->id} {$subscription->name} (due {$dueDate->toDateString()}, H-{$daysRemaining})";

            if ($dryRun) {
                $this->line("Would remind: {$label}");

                continue;
            }

            try {
                Notification::route('mail', $recipient)
                    ->notify(new SubscriptionDueReminderNotification($subscription, $daysRemaining));
            } catch (\Throwable $e) {
                // One bad send (SMTP hiccup, missing data) shouldn't stop the
                // rest; it stays unmarked and is retried on the next run.
                Log::error('Subscription due reminder failed', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
                $this->error("Failed: {$label} -- {$e->getMessage()}");

                continue;
            }

            $subscription->forceFill(['due_reminder_sent_for' => $subscription->next_due_date])->saveQuietly();

            activity('Business Documents')
                ->performedOn($subscription)
                ->withProperties(['due_date' => $dueDate->toDateString(), 'recipient' => $recipient])
                ->event('due_reminder_sent')
                ->log('sent a subscription due-date reminder email');

            $this->info("Reminded: {$label}");
            $sent++;
        }

        if (! $dryRun) {
            $this->info("Sent {$sent} of {$subscriptions->count()} reminder(s) to {$recipient}.");
        }

        return self::SUCCESS;
    }
}
