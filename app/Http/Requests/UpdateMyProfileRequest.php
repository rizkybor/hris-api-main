<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Models\ConfigurableOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Self-service profile update (PUT /me). Besides the account fields, an
 * employee may edit their own personal details, address, preferences and
 * emergency contacts. Identity number, NPWP, employee code, job, salary
 * and bank details stay HR-only (EmployeeProfileUpdateRequest).
 *
 * Every employee field is optional ("sometimes"), so clients that only
 * send name/password/photo keep working; rules match the HR form.
 */
class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Account
            // The full name as typed, titles/degrees included.
            'name' => ['required', 'string', 'max:255'],
            // Show those titles/degrees wherever the name appears in HRIS.
            'show_degree' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],

            // Personal details
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'place_of_birth' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(Gender::cases(), 'value'))],
            'hobby' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Address
            'address' => ['sometimes', 'required', 'string'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'required', 'string', 'max:10'],

            // Preferences
            'preferred_language' => ['sometimes', 'nullable', 'string', Rule::in($this->configurableValues('preferred_language'))],
            'additional_notes' => ['sometimes', 'nullable', 'string'],

            // Emergency contacts: the full list; contacts left out are removed.
            'emergency_contacts' => ['sometimes', 'nullable', 'array'],
            'emergency_contacts.*.id' => ['nullable', 'integer'],
            'emergency_contacts.*.full_name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['required', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:20'],
            'emergency_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'password' => 'Password',
            'profile_photo' => 'Profile Photo',
            'phone' => 'Phone Number',
            'date_of_birth' => 'Date of Birth',
            'place_of_birth' => 'Place of Birth',
            'gender' => 'Gender',
            'hobby' => 'Hobby',
            'address' => 'Address',
            'city' => 'City',
            'postal_code' => 'Postal Code',
            'preferred_language' => 'Preferred Language',
            'additional_notes' => 'Additional Notes',
            'emergency_contacts' => 'Emergency Contacts',
            'emergency_contacts.*.full_name' => 'Full Name',
            'emergency_contacts.*.relationship' => 'Relationship',
            'emergency_contacts.*.phone' => 'Phone Number',
            'emergency_contacts.*.email' => 'Email',
        ];
    }

    /**
     * Active values of a configurable dropdown (Settings > Dropdown Options).
     */
    private function configurableValues(string $category): array
    {
        return ConfigurableOption::category($category)->active()->pluck('value')->all();
    }
}
