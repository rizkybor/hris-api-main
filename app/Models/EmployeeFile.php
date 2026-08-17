<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeFile extends Model
{
    protected $fillable = [
        'employee_id',
        'original_name',
        'display_name',
        'file_path',
        'mime_type',
        'size_file',
        'uploaded_by',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
