<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a users.id value that points at the protected Super Admin
 * account -- used for fields like team_lead_id that reference users
 * directly (not through employee_profiles).
 */
class NotProtectedUser implements ValidationRule
{
    public function __construct(private string $subject = 'this account') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        if (User::find($value)?->isProtected()) {
            $fail("The Super Admin account cannot be set as {$this->subject}.");
        }
    }
}
