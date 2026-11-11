<?php

namespace App\Http\Requests;

/**
 * Employee profile columns an employee may change themselves (PUT /me).
 * Kept in one place so the request, controller and repository agree.
 */
final class UpdateMyProfileFields
{
    public const EMPLOYEE = [
        'phone',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'hobby',
        'address',
        'city',
        'postal_code',
        'preferred_language',
        'additional_notes',
    ];
}
