<?php

namespace App\DTOs;

use App\Models\EmployeeProfile;

class EmployeeProfileDto
{
    public function __construct(
        public readonly string $user_id,
        public readonly string $code,
        public readonly string $identity_number,
        public readonly ?string $npwp,
        public readonly string $phone,
        public readonly string $date_of_birth,
        public readonly string $gender,
        public readonly ?string $hobby,
        public readonly string $place_of_birth,
        public readonly string $address,
        public readonly string $city,
        public readonly string $postal_code,
        public readonly ?string $profile_photo,
        public readonly ?string $preferred_language,
        public readonly ?string $additional_notes,
        // Job Information
        public readonly string $job_title,
        public readonly ?int $team_id,
        public readonly int $years_experience,
        public readonly string $status,
        public readonly string $employment_type,
        public readonly string $work_location,
        public readonly string $start_date,
        public readonly ?float $monthly_salary,
        public readonly string $skill_level,
        // Bank Information (optional -- e.g. interns without a payroll account yet)
        public readonly ?string $bank_name,
        public readonly ?string $account_number,
        public readonly ?string $account_holder_name,
        public readonly ?string $bank_branch,
        public readonly ?string $account_type,
        // Emergency Contacts
        public readonly array $emergency_contacts = [],
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'code' => $this->code,
            'identity_number' => $this->identity_number,
            'npwp' => $this->npwp,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'hobby' => $this->hobby,
            'place_of_birth' => $this->place_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'profile_photo' => $this->profile_photo,
            'preferred_language' => $this->preferred_language,
            'additional_notes' => $this->additional_notes,
            // Job Information
            'job_title' => $this->job_title,
            'team_id' => $this->team_id,
            'years_experience' => $this->years_experience,
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'start_date' => $this->start_date,
            'monthly_salary' => $this->monthly_salary,
            'skill_level' => $this->skill_level,
            // Bank Information
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_holder_name' => $this->account_holder_name,
            'bank_branch' => $this->bank_branch,
            'account_type' => $this->account_type,
            // Emergency Contacts
            'emergency_contacts' => $this->emergency_contacts,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'],
            code: $data['code'],
            identity_number: $data['identity_number'],
            npwp: $data['npwp'] ?? null,
            phone: $data['phone'],
            date_of_birth: $data['date_of_birth'],
            gender: $data['gender'],
            hobby: $data['hobby'] ?? null,
            place_of_birth: $data['place_of_birth'],
            address: $data['address'],
            city: $data['city'],
            postal_code: $data['postal_code'],
            profile_photo: $data['profile_photo'] ?? null,
            preferred_language: $data['preferred_language'] ?? null,
            additional_notes: $data['additional_notes'] ?? null,
            // Job Information
            job_title: $data['job_title'],
            team_id: $data['team_id'] ?? null,
            years_experience: $data['years_experience'],
            status: $data['status'],
            employment_type: $data['employment_type'],
            work_location: $data['work_location'],
            start_date: $data['start_date'],
            monthly_salary: isset($data['monthly_salary']) ? (float) $data['monthly_salary'] : null,
            skill_level: $data['skill_level'],
            // Bank Information
            bank_name: $data['bank_name'] ?? null,
            account_number: $data['account_number'] ?? null,
            account_holder_name: $data['account_holder_name'] ?? null,
            bank_branch: $data['bank_branch'] ?? null,
            account_type: $data['account_type'] ?? null,
            // Emergency Contacts
            emergency_contacts: $data['emergency_contacts'] ?? [],
        );
    }

    public static function fromArrayForUpdate(array $data, EmployeeProfile $existingProfile): self
    {
        return new self(
            user_id: $data['user_id'] ?? $existingProfile->user_id,
            code: $data['code'] ?? $existingProfile->code ?? '',
            identity_number: $data['identity_number'] ?? $existingProfile->identity_number,
            npwp: array_key_exists('npwp', $data) ? $data['npwp'] : $existingProfile->npwp,
            phone: $data['phone'] ?? $existingProfile->phone,
            date_of_birth: $data['date_of_birth'] ?? ($existingProfile->date_of_birth ? $existingProfile->date_of_birth->format('Y-m-d') : null),
            gender: $data['gender'] ?? $existingProfile->gender,
            hobby: $data['hobby'] ?? $existingProfile->hobby,
            place_of_birth: $data['place_of_birth'] ?? $existingProfile->place_of_birth,
            address: $data['address'] ?? $existingProfile->address,
            city: $data['city'] ?? $existingProfile->city,
            postal_code: $data['postal_code'] ?? $existingProfile->postal_code,
            profile_photo: $data['profile_photo'] ?? $existingProfile->profile_photo,
            preferred_language: $data['preferred_language'] ?? $existingProfile->preferred_language,
            additional_notes: $data['additional_notes'] ?? $existingProfile->additional_notes,
            // Job Information
            job_title: $data['job_title'] ?? $existingProfile->jobInformation?->job_title ?? '',
            team_id: $data['team_id'] ?? $existingProfile->jobInformation?->team_id,
            years_experience: $data['years_experience'] ?? $existingProfile->jobInformation?->years_experience ?? 0,
            status: $data['status'] ?? $existingProfile->jobInformation?->status ?? 'active',
            employment_type: $data['employment_type'] ?? $existingProfile->jobInformation?->employment_type ?? 'full_time',
            work_location: $data['work_location'] ?? $existingProfile->jobInformation?->work_location ?? 'office',
            start_date: $data['start_date'] ?? ($existingProfile->jobInformation?->start_date ? $existingProfile->jobInformation->start_date->format('Y-m-d') : now()->format('Y-m-d')),
            // array_key_exists (not isset/??) so explicitly clearing a field
            // to blank actually saves as null instead of silently keeping
            // the previous value.
            monthly_salary: array_key_exists('monthly_salary', $data) ? (is_null($data['monthly_salary']) ? null : (float) $data['monthly_salary']) : $existingProfile->jobInformation?->monthly_salary,
            skill_level: $data['skill_level'] ?? $existingProfile->jobInformation?->skill_level ?? 'beginner',
            // Bank Information
            bank_name: array_key_exists('bank_name', $data) ? $data['bank_name'] : $existingProfile->bankInformation?->bank_name,
            account_number: array_key_exists('account_number', $data) ? $data['account_number'] : $existingProfile->bankInformation?->account_number,
            account_holder_name: array_key_exists('account_holder_name', $data) ? $data['account_holder_name'] : $existingProfile->bankInformation?->account_holder_name,
            bank_branch: $data['bank_branch'] ?? $existingProfile->bankInformation?->bank_branch,
            account_type: array_key_exists('account_type', $data) ? $data['account_type'] : $existingProfile->bankInformation?->account_type,
            // Emergency Contacts
            emergency_contacts: $data['emergency_contacts'] ?? $existingProfile->emergency_contacts ?? [],
        );
    }
}
