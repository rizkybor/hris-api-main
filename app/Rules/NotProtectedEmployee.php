<?php

namespace App\Rules;

use App\Models\EmployeeProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects an employee_profiles.id value whose linked user is the
 * protected Super Admin account -- used for fields like project_leader_id
 * and team member employee_id.
 */
class NotProtectedEmployee implements ValidationRule
{
    public function __construct(private string $subject = 'this account') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $employee = EmployeeProfile::with('user')->find($value);

        if ($employee?->user?->isProtected()) {
            $fail("The Super Admin account cannot be set as {$this->subject}.");
        }
    }
}
