<?php

namespace App\Notifications;

use App\Models\MeetingNoteComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MeetingNoteCommentMention extends Notification implements ShouldQueue
{
    use Queueable;

    protected MeetingNoteComment $comment;

    public function __construct(MeetingNoteComment $comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $note = $this->comment->meetingNote;
        $commenterName = $this->comment->user?->name ?? 'Someone';

        return [
            'title' => 'You were mentioned in a comment',
            'message' => $commenterName.' mentioned you in a comment on "'.$note?->title.'"',
            'url' => '/admin/documents/meeting-notes/'.$this->comment->meeting_note_id.'?comment='.$this->comment->id,
            'meeting_note_id' => $this->comment->meeting_note_id,
            'comment_id' => $this->comment->id,
        ];
    }
}
