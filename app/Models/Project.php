<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Project extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project');
    }

    protected $fillable = [
        'name',
        'type',
        'priority',
        'status',
        'start_date',
        'end_date',
        'description',
        'photo',
        'budget',
        'project_leader_id',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    public function projectLeader()
    {
        return $this->belongsTo(EmployeeProfile::class, 'project_leader_id');
    }

    // Alias for projectLeader for backward compatibility
    public function leader()
    {
        return $this->projectLeader();
    }

    public function projectTeams()
    {
        return $this->hasMany(ProjectTeam::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'project_teams', 'project_id', 'team_id')
            ->withPivot('assigned_at');
    }

    /**
     * Only employees assigned to one of this project's teams (via Team
     * Assignments, set up when the project was created/edited) may comment
     * on or be mentioned in this project's tasks.
     */
    public function hasEmployeeAssignedToTeam(int $employeeId): bool
    {
        return TeamMember::whereNull('left_at')
            ->where('employee_id', $employeeId)
            ->whereHas('team.projects', function ($query) {
                $query->where('projects.id', $this->id);
            })
            ->exists();
    }

    public function isProjectLeader(int $employeeId): bool
    {
        return $this->project_leader_id === $employeeId;
    }

    /**
     * Who may comment on / be mentioned in this project's tasks: employees
     * assigned to one of the project's teams, or the project leader.
     * Managers additionally bypass this on any project (checked separately
     * via the user's role, since it isn't project-scoped).
     */
    public function isEmployeeProjectParticipant(int $employeeId): bool
    {
        return $this->isProjectLeader($employeeId) || $this->hasEmployeeAssignedToTeam($employeeId);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }
}
