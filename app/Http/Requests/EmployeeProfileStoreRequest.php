<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\Gender;
use App\Enums\JobStatus;
use App\Enums\SkillLevel;
use App\Enums\WorkLocation;
use App\Models\ConfigurableOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class EmployeeProfileStoreRequest extends FormRequest
{
    /**
     * Optional select/number fields submit as an empty string when left
     * blank; normalize those to null so `nullable` rules actually skip them
     * instead of failing on an empty string.
     */
    protected function prepareForValidation(): void
    {
        $optionalFields = ['bank_name', 'account_number', 'account_holder_name', 'bank_branch', 'account_type', 'monthly_salary', 'ptkp_status', 'team_id'];

        $blanked = collect($optionalFields)
            ->filter(fn ($field) => $this->has($field) && $this->input($field) === '')
            ->mapWithKeys(fn ($field) => [$field => null]);

        if ($blanked->isNotEmpty()) {
            $this->merge($blanked->toArray());
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // User fields
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'roles' => ['required', 'array'],
            'roles.*' => ['required', 'string', Rule::in(Role::where('name', '!=', 'superadmin')->pluck('name'))],

            // Employee Profile fields
            'identity_number' => ['required', 'string', 'max:20', 'unique:employee_profiles,identity_number'],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:'.implode(',', array_column(Gender::cases(), 'value'))],
            'hobby' => ['nullable', 'string', 'max:255'],
            'place_of_birth' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'preferred_language' => ['nullable', 'string', Rule::in($this->configurableValues('preferred_language'))],
            'additional_notes' => ['nullable', 'string'],

            // Job Information fields
            'job_title' => ['required', 'string', 'max:255'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'years_experience' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(JobStatus::cases(), 'value'))],
            'employment_type' => ['required', 'string', Rule::in($this->configurableValues('employment_type'))],
            'work_location' => ['required', 'string', 'in:'.implode(',', array_column(WorkLocation::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'skill_level' => ['required', 'string', 'in:'.implode(',', array_column(SkillLevel::cases(), 'value'))],
            'ptkp_status' => ['nullable', 'string', Rule::in($this->configurableValues('ptkp_status'))],
            'annual_leave_quota' => ['nullable', 'integer', 'min:0', 'max:365'],
            'probation_end_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date'],

            // Bank Information fields (optional -- e.g. interns without a payroll account yet)
            'bank_name' => ['nullable', 'string', Rule::in($this->configurableValues('bank_name'))],
            'account_number' => ['nullable', 'string', 'max:50', 'unique:bank_information,account_number'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'in:'.implode(',', array_column(AccountType::cases(), 'value'))],

            // Emergency Contacts fields (array, optional -- e.g. employee hasn't provided one yet)
            'emergency_contacts' => ['nullable', 'array'],
            'emergency_contacts.*.full_name' => ['nullable', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['required_with:emergency_contacts.*.full_name,emergency_contacts.*.phone,emergency_contacts.*.email', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['required_with:emergency_contacts.*.full_name,emergency_contacts.*.relationship,emergency_contacts.*.email', 'string', 'max:20'],
            'emergency_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * Active values for a manager-configurable dropdown category (see
     * Settings > Dropdown Options), used so validation always matches
     * whatever options are currently enabled.
     */
    private function configurableValues(string $category): array
    {
        return ConfigurableOption::category($category)->active()->pluck('value')->all();
    }

    public function attributes()
    {
        return [
            // User attributes
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'user_profile_photo' => 'User Profile Photo',

            // Employee Profile attributes
            'code' => 'Employee Code',
            'identity_number' => 'Identity Number',
            'phone' => 'Phone Number',
            'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender',
            'hobby' => 'Hobby',
            'place_of_birth' => 'Place of Birth',
            'address' => 'Address',
            'city' => 'City',
            'postal_code' => 'Postal Code',
            'profile_photo' => 'Profile Photo',
            'preferred_language' => 'Preferred Language',
            'additional_notes' => 'Additional Notes',

            // Job Information attributes
            'job_title' => 'Job Title',
            'team_id' => 'Team',
            'years_experience' => 'Years of Experience',
            'status' => 'Job Status',
            'employment_type' => 'Employment Type',
            'work_location' => 'Work Location',
            'start_date' => 'Start Date',
            'monthly_salary' => 'Monthly Salary',
            'skill_level' => 'Skill Level',

            // Bank Information attributes
            'bank_name' => 'Bank Name',
            'account_number' => 'Account Number',
            'account_holder_name' => 'Account Holder Name',
            'bank_branch' => 'Bank Branch',
            'account_type' => 'Account Type',

            // Emergency Contacts attributes
            'emergency_contacts' => 'Emergency Contacts',
            'emergency_contacts.*.full_name' => 'Full Name',
            'emergency_contacts.*.relationship' => 'Relationship',
            'emergency_contacts.*.phone' => 'Phone Number',
            'emergency_contacts.*.email' => 'Email',
        ];
    }
}
