<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Password HRIS+')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Kami menerima permintaan untuk mereset password akun kamu.')
            ->action('Reset Password', $this->url)
            ->line('Link ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika kamu tidak meminta reset password, abaikan email ini.')
            ->salutation('Terima kasih, Tim HRIS+');
    }
}
