<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Proposal extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected'];

    public function getActivitylogOptions(): LogOptions
    {
        // `sections` is left out: it's the whole proposal body and changes
        // on every save, which would bloat the audit trail.
        return LogOptions::defaults()
            ->logOnly(['title', 'client_name', 'proposal_date', 'letter_number', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Business Documents');
    }

    protected $fillable = [
        'title',
        'client_id',
        'client_name',
        'proposal_date',
        'letter_number',
        'status',
        'cover_image',
        'sections',
        'signatory_name',
        'signatory_title',
        'use_signature',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'proposal_date' => 'date',
            'sections' => 'array',
            'use_signature' => 'boolean',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', '%'.$search.'%')
                ->orWhere('client_name', 'like', '%'.$search.'%')
                ->orWhere('letter_number', 'like', '%'.$search.'%');
        });
    }

    /** "Proposal Penawaran — Sistem Manajemen Order - PT Misael Berkat Abadi" */
    public function documentTitle(): string
    {
        return 'Proposal Penawaran — '.$this->title.' - '.$this->client_name;
    }
}
