<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingNoteViewer extends Model
{
    protected $fillable = [
        'meeting_note_id',
        'user_id',
        'is_editing',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_editing' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('last_seen_at', '>=', now()->subSeconds(MeetingNote::ACTIVE_VIEWER_WINDOW_SECONDS));
    }
}
