<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InfrastructureToolReminderNotification extends Notification
{
    use Queueable;

    protected array $tools;
    protected string $targetDate;

    public function __construct(array $tools, string $targetDate)
    {
        $this->tools = $tools;
        $this->targetDate = $targetDate;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

public function toMail($notifiable)
{
    $actionUrl = 'https://backoffice.jcdigital.co.id/admin/company-finance';

    return (new MailMessage)
        ->subject('Reminder: Infrastructure Tool Expiring on ' . $this->targetDate)
        ->markdown('emails.reminders.infrastructure_tool', [
            'tools' => $this->tools,
            'targetDate' => $this->targetDate,
            'actionUrl' => $actionUrl,
        ]);
}

}
