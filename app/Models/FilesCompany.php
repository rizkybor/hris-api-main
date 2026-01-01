<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FilesCompany extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_name',
        'document_path',
        'type_file',
        'size_file',
        'description'
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('document_name', 'like', '%'.$search.'%')
                ->orWhere('type_file', 'like', '%'.$search.'%')
                ->orWhere('size_file', 'like', '%'.$search.'%');
        });
    }
}