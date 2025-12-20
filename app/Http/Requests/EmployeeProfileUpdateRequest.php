<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\BankName;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\JobStatus;
use App\Enums\SkillLevel;
use App\Enums\WorkLocation;
use App\Models\EmployeeProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employeeId = $this->route('employee');
        $employee = EmployeeProfile::find($employeeId);
        $userId = $employee?->user_id;

        return [
            // User fields
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'roles' => ['sometimes', 'required', 'array'],
            'roles.*' => ['required', 'string', 'in:hr,finance,employee'],

            // Employee Profile fields
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('employee_profiles', 'code')->ignore($employeeId)],
            'identity_number' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('employee_profiles', 'identity_number')->ignore($employeeId)],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'gender' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(Gender::cases(), 'value'))],
            'hobby' => ['nullable', 'string', 'max:255'],
            'place_of_birth' => ['sometimes', 'required', 'string', 'max:100'],
            'address' => ['sometimes', 'required', 'string'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'required', 'string', 'max:10'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'additional_notes' => ['nullable', 'string'],

            // Job Information fields
            'job_title' => ['sometimes', 'required', 'string', 'max:255'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'years_experience' => ['sometimes', 'required', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(JobStatus::cases(), 'value'))],
            'employment_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))],
            'work_location' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(WorkLocation::cases(), 'value'))],
            'start_date' => ['sometimes', 'required', 'date'],
            'monthly_salary' => ['sometimes', 'required', 'numeric', 'min:0'],
            'skill_level' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(SkillLevel::cases(), 'value'))],

            // Bank Information fields
            'bank_name' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(BankName::cases(), 'value'))],
            'account_number' => ['sometimes', 'required', 'string', 'max:50'],
            'account_holder_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(AccountType::cases(), 'value'))],

            // Emergency Contacts fields (array)
            'emergency_contacts' => ['sometimes', 'required', 'array', 'min:1'],
            'emergency_contacts.*.id' => ['nullable', 'integer', 'exists:emergency_contacts,id'],
            'emergency_contacts.*.full_name' => ['sometimes', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['sometimes', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['sometimes', 'string', 'max:20'],
            'emergency_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
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
            'team' => 'Team',
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
