<?php

namespace App\Notifications;

use App\Models\DocumentLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentLetterRejected extends Notification implements ShouldQueue
{
    use Queueable;

    protected DocumentLetter $documentLetter;

    public function __construct(DocumentLetter $documentLetter)
    {
        $this->documentLetter = $documentLetter;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Official Memo Ditolak')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Official Memo yang kamu ajukan telah ditolak oleh Finance Manager.')
            ->line('**Detail Official Memo:**')
            ->line('Nomor: '.$this->documentLetter->document_number)
            ->line('Perihal: '.$this->documentLetter->subject)
            ->line('Alasan Penolakan: '.($this->documentLetter->rejection_reason ?? '-'))
            ->action('Lihat Detail', url('/admin/documents/official-memos/'.$this->documentLetter->id))
            ->salutation('Terima kasih, Tim HRIS+');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Official Memo Ditolak',
            'message' => 'Official Memo "'.$this->documentLetter->subject.'" telah ditolak.',
            'url' => '/admin/documents/official-memos/'.$this->documentLetter->id,
            'document_letter_id' => $this->documentLetter->id,
            'document_number' => $this->documentLetter->document_number,
            'rejection_reason' => $this->documentLetter->rejection_reason,
            'status' => 'rejected',
        ];
    }
}
