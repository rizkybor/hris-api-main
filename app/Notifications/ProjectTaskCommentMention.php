<?php

namespace App\Notifications;

use App\Models\ProjectTaskComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectTaskCommentMention extends Notification implements ShouldQueue
{
    use Queueable;

    protected ProjectTaskComment $comment;

    public function __construct(ProjectTaskComment $comment)
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
            'message' => $commenterName.' mentioned you in a comment on "'.$task?->name.'"',
            'url' => '/admin/projects/'.$task?->project_id,
            'project_task_id' => $this->comment->project_task_id,
            'comment_id' => $this->comment->id,
        ];
    }
}
