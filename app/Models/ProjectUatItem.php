<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One finding (temuan) or note (catatan) of a UAT round. */
class ProjectUatItem extends Model
{
    public const TYPE_FINDING = 'finding';

    public const TYPE_NOTE = 'note';

    public const TYPES = [self::TYPE_FINDING, self::TYPE_NOTE];

    /** Proof photos: images only, up to this many KB (the browser shrinks them first). */
    public const PHOTO_MAX_KB = 2048;

    protected $fillable = ['project_uat_session_id', 'project_uat_location_id', 'position', 'type', 'feature', 'menu', 'description', 'is_resolved', 'resolved_at', 'resolved_by', 'photo_path', 'photo_name'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_resolved' => 'boolean', 'resolved_at' => 'datetime'];
    }

    public function location()
    {
        return $this->belongsTo(ProjectUatLocation::class, 'project_uat_location_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function session()
    {
        return $this->belongsTo(ProjectUatSession::class, 'project_uat_session_id');
    }
}
