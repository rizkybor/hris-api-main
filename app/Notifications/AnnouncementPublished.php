<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    protected Announcement $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
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
        return [
            'title' => $this->announcement->title,
            'message' => str($this->announcement->body)->limit(140)->toString(),
            'url' => '/admin/announcements',
            'announcement_id' => $this->announcement->id,
        ];
    }
}
