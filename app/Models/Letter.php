<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use SoftDeletes;

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
        'body',
        'items',
        'signatory_name',
        'signatory_title',
        'second_party_name',
        'second_party_signatory_name',
        'second_party_signatory_title',
        'status',
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

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('letter_number', 'like', '%'.$search.'%')
                ->orWhere('subject', 'like', '%'.$search.'%')
                ->orWhere('recipient', 'like', '%'.$search.'%');
        });
    }
}
