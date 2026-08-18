<?php

namespace App\Notifications;

use App\Models\DocumentLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentLetterSubmitted extends Notification implements ShouldQueue
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
            ->subject('Official Memo Baru Menunggu Persetujuan')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Sebuah Official Memo baru telah diajukan dan menunggu persetujuan kamu.')
            ->line('**Detail Official Memo:**')
            ->line('Nomor: '.$this->documentLetter->document_number)
            ->line('Perihal: '.$this->documentLetter->subject)
            ->line('Pembuat: '.($this->documentLetter->creator?->name ?? '-'))
            ->action('Tinjau Official Memo', url('/admin/documents/official-memos/'.$this->documentLetter->id))
            ->salutation('Terima kasih, Tim HRIS');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Official Memo Baru',
            'message' => 'Official Memo "'.$this->documentLetter->subject.'" menunggu persetujuan kamu.',
            'url' => '/admin/documents/official-memos/'.$this->documentLetter->id,
            'document_letter_id' => $this->documentLetter->id,
            'document_number' => $this->documentLetter->document_number,
            'status' => $this->documentLetter->status,
        ];
    }
}
