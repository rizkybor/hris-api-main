<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingNoteAttachment extends Model
{
    protected $fillable = [
        'meeting_note_id',
        'original_name',
        'file_path',
        'mime_type',
        'size_file',
        'uploaded_by',
    ];

    public function meetingNote()
    {
        return $this->belongsTo(MeetingNote::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
