<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingNoteComment extends Model
{
    protected $fillable = [
        'meeting_note_id',
        'parent_id',
        'user_id',
        'body',
        'mentioned_employee_ids',
    ];

    protected function casts(): array
    {
        return [
            'mentioned_employee_ids' => 'array',
        ];
    }

    public function meetingNote()
    {
        return $this->belongsTo(MeetingNote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(MeetingNoteComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(MeetingNoteComment::class, 'parent_id')->orderBy('created_at');
    }

    public function mentionedEmployees()
    {
        return EmployeeProfile::with('user')
            ->whereIn('id', $this->mentioned_employee_ids ?? [])
            ->get();
    }
}
