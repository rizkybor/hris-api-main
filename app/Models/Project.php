<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

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

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }
}
