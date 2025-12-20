<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\BankName;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\JobStatus;
use App\Enums\SkillLevel;
use App\Enums\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeProfileStoreRequest extends FormRequest
{
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
            'password' => ['required', 'string', 'min:8'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'roles' => ['required', 'array'],
            'roles.*' => ['required', 'string', 'in:hr,finance,employee'],

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
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'additional_notes' => ['nullable', 'string'],

            // Job Information fields
            'job_title' => ['required', 'string', 'max:255'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'years_experience' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(JobStatus::cases(), 'value'))],
            'employment_type' => ['required', 'string', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))],
            'work_location' => ['required', 'string', 'in:'.implode(',', array_column(WorkLocation::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'skill_level' => ['required', 'string', 'in:'.implode(',', array_column(SkillLevel::cases(), 'value'))],

            // Bank Information fields
            'bank_name' => ['required', 'string', 'in:'.implode(',', array_column(BankName::cases(), 'value'))],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_type' => ['required', 'string', 'in:'.implode(',', array_column(AccountType::cases(), 'value'))],

            // Emergency Contacts fields (array)
            'emergency_contacts' => ['required', 'array', 'min:1'],
            'emergency_contacts.*.full_name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['required', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:20'],
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
