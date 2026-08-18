<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MeetingNote extends Model
{
    use SoftDeletes, LogsActivity;

    /**
     * A viewer/editor heartbeat older than this is no longer considered
     * "active" for the presence indicator.
     */
    public const ACTIVE_VIEWER_WINDOW_SECONDS = 20;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Meeting Note');
    }

    protected $fillable = [
        'document_number',
        'title',
        'meeting_type',
        'meeting_date',
        'body',
        'action_items',
        'external_attendees',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'datetime',
            'action_items' => 'array',
            'external_attendees' => 'array',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attendees()
    {
        return $this->belongsToMany(EmployeeProfile::class, 'meeting_note_attendees', 'meeting_note_id', 'employee_id');
    }

    public function comments()
    {
        return $this->hasMany(MeetingNoteComment::class);
    }

    /**
     * Only employees checked off in "Internal Attendees" may comment on or
     * be mentioned in this note -- mirrors Project::isEmployeeProjectParticipant.
     */
    public function isEmployeeAttendee(int $employeeId): bool
    {
        return $this->attendees()->where('employee_profiles.id', $employeeId)->exists();
    }

    public function attachments()
    {
        return $this->hasMany(MeetingNoteAttachment::class);
    }

    public function pinnedByUsers()
    {
        return $this->belongsToMany(User::class, 'user_pinned_notes', 'document_id', 'user_id')->withTimestamps();
    }

    public function viewers()
    {
        return $this->hasMany(MeetingNoteViewer::class);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('document_number', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%');
        });
    }
}
