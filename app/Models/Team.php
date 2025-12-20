<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\TeamStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'expected_size',
        'description',
        'icon',
        'department',
        'status',
        'team_lead_id',
        'responsibilities',
    ];

    protected function casts(): array
    {
        return [
            'responsibilities' => 'array',
            'department' => Department::class,
            'status' => TeamStatus::class,
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function members()
    {
        return $this->hasMany(TeamMember::class)->whereNull('left_at');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_teams', 'team_id', 'project_id')
            ->withPivot('assigned_at');
    }
}
