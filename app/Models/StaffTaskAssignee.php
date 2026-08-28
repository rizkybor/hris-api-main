<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTaskAssignee extends Model
{
    protected $fillable = [
        'staff_task_id',
        'employee_id',
        'status',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'status_updated_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(StaffTask::class, 'staff_task_id');
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
