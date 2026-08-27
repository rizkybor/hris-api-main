<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Letter extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Business Documents');
    }

    protected $fillable = [
        'letter_number',
        'letter_code_id',
        'division_code_id',
        'type',
        'year',
        'sequence',
        'date',
        'subject',
        'recipient',
        'employee_id',
        'body',
        'items',
        'signatory_name',
        'signatory_title',
        'second_party_name',
        'second_party_signatory_name',
        'second_party_signatory_title',
        'status',
        'template',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'items' => 'array',
        ];
    }

    public function letterCode()
    {
        return $this->belongsTo(LetterCode::class);
    }

    public function divisionCode()
    {
        return $this->belongsTo(DivisionCode::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('letter_number', 'like', '%'.$search.'%')
                ->orWhere('subject', 'like', '%'.$search.'%')
                ->orWhere('recipient', 'like', '%'.$search.'%');
        });
    }
}
