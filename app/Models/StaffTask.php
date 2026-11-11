<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'due_date',
        'assignment_mode',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees()
    {
        return $this->hasMany(StaffTaskAssignee::class);
    }

    public function comments()
    {
        return $this->hasMany(StaffTaskComment::class);
    }

    public function isEmployeeAssignee(int $employeeId): bool
    {
        return $this->assignees()->where('employee_id', $employeeId)->exists();
    }

    /**
     * Rich-text HTML from the editor, rendered with v-html: cleaned on the
     * way in, and on the way out for anything saved before this was added
     * (scripts, event handlers, javascript: links are stripped).
     */
    protected function description(): Attribute
    {
        $clean = fn ($value) => $value === null || $value === '' ? $value : HtmlSanitizer::clean($value);

        return Attribute::make(get: $clean, set: $clean);
    }
}
