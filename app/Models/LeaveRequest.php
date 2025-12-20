<?php

namespace App\Models;

use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'emergency_contact',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('reason', 'like', "%{$search}%")
                ->orWhere('leave_type', 'like', "%{$search}%")
                ->orWhere('emergency_contact', 'like', "%{$search}%")
                ->orWhereHas('employee.user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(EmployeeProfile::class, 'approved_by');
    }
}
