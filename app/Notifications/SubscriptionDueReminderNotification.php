<?php

namespace App\Notifications;

use App\Models\BankAccount;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "This subscription is due in N days" email to the company inbox, with
 * everything needed to act on the bill without opening the backoffice:
 * client and contact details, every service line, the exact totals the
 * invoice will carry, and the payment details.
 */
class SubscriptionDueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Subscription $subscription,
        protected int $daysRemaining,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $dueDate = $subscription->next_due_date->copy()->locale('id')->translatedFormat('d F Y');
        $clientName = $subscription->client?->name ?? '-';

        $bankAccount = $subscription->bank_name
            ? BankAccount::where('bank_name', $subscription->bank_name)->first()
            : null;

        $billing = $subscription->billingBreakdown();
        $pph23Estimate = $subscription->pph23_type
            ? round($billing['total'] * (float) $subscription->pph23_percent / 100)
            : null;

        return (new MailMessage)
            ->subject("[Reminder H-{$this->daysRemaining}] Tagihan {$subscription->name} – {$clientName} jatuh tempo {$dueDate}")
            ->markdown('emails.reminders.subscription_due', [
                'subscription' => $subscription,
                'daysRemaining' => $this->daysRemaining,
                'dueDate' => $dueDate,
                'billing' => $billing,
                'bankAccount' => $bankAccount,
                'pph23Estimate' => $pph23Estimate,
                'actionUrl' => rtrim(config('app.frontend_url'), '/').'/admin/subscriptions',
            ]);
    }
}
