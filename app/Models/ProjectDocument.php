<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'document_name',
        'document_path',
        'type_file',
        'size_file',
        'description',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('document_name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');
        });
    }
}
