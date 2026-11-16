<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reviewer's optional closing note on an employee's Staff Report. */
class StaffRaportReview extends Model
{
    protected $fillable = ['employee_id', 'reviewer_id', 'review'];

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    /** For the PDF and the UI: reviewer name, role and the note. */
    public function toDisplayArray(): array
    {
        $role = $this->reviewer?->getRoleNames()->first(fn ($r) => in_array($r, ['manager', 'operational_director', 'finance']));

        return [
            'id' => $this->id,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $this->reviewer?->name,
            'reviewer_role' => $role,
            'review' => $this->review,
            'updated_at' => $this->updated_at,
        ];
    }
}
