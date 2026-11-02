<?php

namespace App\Notifications;

use App\Models\Screening;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A client sent the screening form: an email to the company inbox
 * (services.screening.notify_email) and an in-app notice for Managers.
 */
class ScreeningSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Screening $screening) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database'];
    }

    private function url(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/admin/client-screening/'.$this->screening->id;
    }

    private function needs(): string
    {
        $s = $this->screening;
        $question = collect($s->template_snapshot['sections'] ?? [])->flatMap(fn ($x) => $x['questions'] ?? [])->firstWhere('key', 'needs');

        return collect($question['options'] ?? [])
            ->filter(fn ($o) => in_array($o['value'], (array) ($s->answers['needs'] ?? []), true))
            ->map(fn ($o) => $o['label']['id'] ?? $o['value'])
            ->implode(', ') ?: '-';
    }

    /** Text typed by the client, so it can't turn into links/formatting in the email. */
    private static function plain(?string $text): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~])/', '\\\\$1', (string) $text);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->screening;
        $whoText = trim(($s->contact_name ?: 'Tanpa nama').($s->company ? ' — '.$s->company : ''));
        $who = self::plain($whoText);

        return (new MailMessage)
            ->subject("Screening kebutuhan baru: {$whoText} ({$s->code})")
            ->greeting('Ada screening kebutuhan baru')
            ->line("**{$who}** mengisi form screening ".($s->source === 'invite' ? 'dari link undangan' : 'di website').'.')
            ->line('Kebutuhan: '.$this->needs())
            ->line('Email: '.self::plain($s->email ?: '-').' · Telepon/WA: '.self::plain($s->phone ?: '-'))
            ->line('Bahasa form: '.strtoupper($s->lang))
            ->action('Buka di HRIS', $this->url())
            ->line('Rekomendasi layanan dan estimasi harga sudah disiapkan di HRIS (hanya untuk tim internal).');
    }

    public function toArray(object $notifiable): array
    {
        $s = $this->screening;

        return [
            'title' => 'Screening kebutuhan baru',
            'message' => trim(($s->contact_name ?: 'Klien').($s->company ? ' ('.$s->company.')' : '')).' mengisi form screening: '.$this->needs(),
            'url' => '/admin/client-screening/'.$s->id,
            'screening_id' => $s->id,
        ];
    }
}
