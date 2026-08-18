<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentLetter extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Document Letter');
    }

    protected $fillable = [
        'document_number',
        'subject',
        'document_date',
        'sender_id',
        'body',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function sender()
    {
        return $this->belongsTo(EmployeeProfile::class, 'sender_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentLetterAttachment::class);
    }

    /**
     * Documents a Staff account is allowed to see. Recipient is always
     * Finance Manager (the sole approver), so there's no team-targeting
     * left to check -- Staff only ever sees what they authored themselves,
     * and Staff can't create an Official Memo in the first place.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where('created_by', $user->id);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('document_number', 'like', '%'.$search.'%')
                ->orWhere('subject', 'like', '%'.$search.'%');
        });
    }
}
