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
        'team_assignment_mode',
        'vendor_id',
        'inspect_note',
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
     * Individually-picked project members -- the "By Employee" alternative
     * to "By Team" assignment (see team_assignment_mode). Mutually
     * exclusive with teams() in practice: assigning a project stores one or
     * the other, never both.
     */
    public function members()
    {
        return $this->belongsToMany(EmployeeProfile::class, 'project_members', 'project_id', 'employee_id');
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
     * Only relevant in "employee" assignment mode -- individually-picked
     * members who aren't attached through any Team.
     */
    public function hasDirectMember(int $employeeId): bool
    {
        return $this->members()->where('employee_profiles.id', $employeeId)->exists();
    }

    /**
     * Who may comment on / be mentioned in this project's tasks: employees
     * assigned to one of the project's teams, individually-picked members,
     * or the project leader. Managers additionally bypass this on any
     * project (checked separately via the user's role, since it isn't
     * project-scoped).
     */
    public function isEmployeeProjectParticipant(int $employeeId): bool
    {
        return $this->isProjectLeader($employeeId)
            || $this->hasEmployeeAssignedToTeam($employeeId)
            || $this->hasDirectMember($employeeId);
    }

    /**
     * Batched equivalent of isEmployeeProjectParticipant() -- every employee
     * id eligible to comment on / be mentioned in this project's tasks, in a
     * fixed small number of queries regardless of how many ids are checked
     * against it (unlike calling isEmployeeProjectParticipant() in a loop).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getParticipantEmployeeIds(): \Illuminate\Support\Collection
    {
        $teamMemberIds = TeamMember::whereNull('left_at')
            ->whereHas('team.projects', fn ($query) => $query->where('projects.id', $this->id))
            ->pluck('employee_id');

        $directMemberIds = $this->members()->pluck('employee_profiles.id');

        return $teamMemberIds->merge($directMemberIds)
            ->push($this->project_leader_id)
            ->filter()
            ->unique()
            ->values();
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }

    /**
     * The vendor this project is contracted through -- optional, a project
     * may have no vendor at all.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    /**
     * Invoices billed against this project -- optional, an invoice isn't
     * required to reference a project.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
