<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectTask extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project');
    }

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'image',
        'assignee_id',
        'priority',
        'status',
        'due_date',
        'type',
        'color',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'position' => 'float',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /** The first (primary) assignee; the full list is assignees(). */
    public function assignee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'assignee_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(EmployeeProfile::class, 'project_task_assignees', 'project_task_id', 'employee_id')
            ->withTimestamps()
            ->orderBy('project_task_assignees.id');
    }

    /** Tasks the employee is one of the assignees of. */
    public function scopeAssignedTo($query, int $employeeId)
    {
        return $query->whereIn($this->getTable().'.id', self::assigneeTaskIds($employeeId));
    }

    /** Subquery of the ids of the tasks an employee is assigned to. */
    public static function assigneeTaskIds(int $employeeId)
    {
        return \Illuminate\Support\Facades\DB::table('project_task_assignees')
            ->select('project_task_id')
            ->where('employee_id', $employeeId);
    }

    public function isAssignedTo(?int $employeeId): bool
    {
        if (! $employeeId) {
            return false;
        }
        if ($this->relationLoaded('assignees')) {
            return $this->assignees->contains('id', $employeeId);
        }

        return $this->assignees()->where('employee_profiles.id', $employeeId)->exists();
    }

    public function comments()
    {
        return $this->hasMany(ProjectTaskComment::class)->orderBy('created_at');
    }
}
