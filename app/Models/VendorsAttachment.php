<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorsAttachment extends Model
{
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
            $q->where('document_name', 'like', '%' . $search . '%')
                ->orWhere('type_file', 'like', '%' . $search . '%')
                ->orWhere('size_file', 'like', '%' . $search . '%')
                ->orWhere('description', 'like', '%' . $search . '%');
        });
    }
}
