<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTaskComment extends Model
{
    protected $fillable = [
        'staff_task_id',
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

    public function task()
    {
        return $this->belongsTo(StaffTask::class, 'staff_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(StaffTaskComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(StaffTaskComment::class, 'parent_id')->orderBy('created_at');
    }

    public function mentionedEmployees()
    {
        return EmployeeProfile::with('user')
            ->whereIn('id', $this->mentioned_employee_ids ?? [])
            ->get();
    }
}
