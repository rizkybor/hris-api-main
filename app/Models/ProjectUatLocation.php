<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One place a UAT round was held at (rounds can run in several places at once). */
class ProjectUatLocation extends Model
{
    public const MAX_PER_ROUND = 10;

    protected $fillable = ['project_uat_session_id', 'position', 'location', 'held_on', 'start_time', 'participants'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'held_on' => 'date:Y-m-d'];
    }

    public function session()
    {
        return $this->belongsTo(ProjectUatSession::class, 'project_uat_session_id');
    }

    public function items()
    {
        return $this->hasMany(ProjectUatItem::class, 'project_uat_location_id');
    }
}
