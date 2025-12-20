<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_in_lat',
        'check_in_long',
        'check_out',
        'check_out_lat',
        'check_out_long',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'check_in_lat' => 'decimal:8',
            'check_in_long' => 'decimal:8',
            'check_out_lat' => 'decimal:8',
            'check_out_long' => 'decimal:8',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
