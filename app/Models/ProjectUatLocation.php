<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A session of a UAT: one place (and time) it was held at. A UAT can run
 * several sessions at once; each has its own findings and notes. The name
 * is optional (shown as "Sesi n").
 */
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
        return $this->hasMany(ProjectUatItem::class, 'project_uat_location_id')->orderBy('position')->orderBy('id');
    }
}
