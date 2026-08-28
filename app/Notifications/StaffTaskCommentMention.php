<?php

namespace App\Notifications;

use App\Models\StaffTaskComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StaffTaskCommentMention extends Notification implements ShouldQueue
{
    use Queueable;

    protected StaffTaskComment $comment;

    public function __construct(StaffTaskComment $comment)
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
        $task = $this->comment->task;
        $commenterName = $this->comment->user?->name ?? 'Someone';

        return [
            'title' => 'You were mentioned in a comment',
            'message' => $commenterName.' mentioned you in a comment on "'.$task?->title.'"',
            'url' => '/admin/my-tasks?staff_task='.$this->comment->staff_task_id.'&comment='.$this->comment->id,
            'staff_task_id' => $this->comment->staff_task_id,
            'comment_id' => $this->comment->id,
        ];
    }
}
