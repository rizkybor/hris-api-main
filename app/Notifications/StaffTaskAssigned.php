<?php

namespace App\Notifications;

use App\Models\StaffTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StaffTaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected StaffTask $task;

    public function __construct(StaffTask $task)
    {
        $this->task = $task;
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
        $assignerName = $this->task->creator?->name ?? 'Someone';

        return [
            'title' => 'New task assigned to you',
            'message' => $assignerName.' assigned you a task: "'.$this->task->title.'" (due '.$this->task->due_date->format('d M Y').')',
            'url' => '/admin/my-tasks?staff_task='.$this->task->id,
            'staff_task_id' => $this->task->id,
        ];
    }
}
