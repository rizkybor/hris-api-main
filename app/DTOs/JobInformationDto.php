<?php

namespace App\DTOs;

use App\Models\JobInformation;

class JobInformationDto
{
    public function __construct(
        public readonly string $employee_id,
        public readonly string $job_title,
        public readonly ?string $team_id,
        public readonly int $years_experience,
        public readonly string $status,
        public readonly string $employment_type,
        public readonly string $work_location,
        public readonly string $start_date,
        public readonly ?float $monthly_salary,
        public readonly string $skill_level,
        public readonly ?string $ptkp_status,
        public readonly ?int $annual_leave_quota,
        public readonly ?string $probation_end_date,
        public readonly ?string $contract_end_date,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'job_title' => $this->job_title,
            'team_id' => $this->team_id,
            'years_experience' => $this->years_experience,
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'start_date' => $this->start_date,
            'monthly_salary' => $this->monthly_salary,
            'skill_level' => $this->skill_level,
            'ptkp_status' => $this->ptkp_status,
            'annual_leave_quota' => $this->annual_leave_quota,
            'probation_end_date' => $this->probation_end_date,
            'contract_end_date' => $this->contract_end_date,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            employee_id: $data['employee_id'],
            job_title: $data['job_title'],
            team_id: $data['team_id'] ?? null,
            years_experience: $data['years_experience'],
            status: $data['status'],
            employment_type: $data['employment_type'],
            work_location: $data['work_location'],
            start_date: $data['start_date'],
            monthly_salary: isset($data['monthly_salary']) ? (float) $data['monthly_salary'] : null,
            skill_level: $data['skill_level'],
            ptkp_status: $data['ptkp_status'] ?? 'TK/0',
            // array_key_exists (not ??) so a genuine 0 quota is kept --
            // ?? would be safe here too since 0 isn't null, but matching
            // the explicit style used below for the other nullable fields.
            annual_leave_quota: array_key_exists('annual_leave_quota', $data) ? (is_null($data['annual_leave_quota']) ? null : (int) $data['annual_leave_quota']) : 12,
            probation_end_date: $data['probation_end_date'] ?? null,
            contract_end_date: $data['contract_end_date'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, JobInformation $existingJob): self
    {
        return new self(
            employee_id: $data['employee_id'] ?? $existingJob->employee_id,
            job_title: $data['job_title'] ?? $existingJob->job_title,
            team_id: $data['team_id'] ?? $existingJob->team_id,
            years_experience: $data['years_experience'] ?? $existingJob->years_experience ?? 0,
            status: $data['status'] ?? $existingJob->status ?? 'active',
            employment_type: $data['employment_type'] ?? $existingJob->employment_type ?? 'full_time',
            work_location: $data['work_location'] ?? $existingJob->work_location ?? 'office',
            start_date: $data['start_date'] ?? ($existingJob->start_date ? $existingJob->start_date->format('Y-m-d') : now()->format('Y-m-d')),
            // array_key_exists (not isset/??) so explicitly clearing the
            // field to blank actually saves as null instead of silently
            // keeping the previous salary.
            monthly_salary: array_key_exists('monthly_salary', $data) ? (is_null($data['monthly_salary']) ? null : (float) $data['monthly_salary']) : $existingJob->monthly_salary,
            skill_level: $data['skill_level'] ?? $existingJob->skill_level ?? 'beginner',
            ptkp_status: $data['ptkp_status'] ?? $existingJob->ptkp_status ?? 'TK/0',
            // Same array_key_exists reasoning as monthly_salary above --
            // this is the exact bug being fixed: 0 is a valid quota and
            // must not fall through to the existing/default value.
            annual_leave_quota: array_key_exists('annual_leave_quota', $data) ? (is_null($data['annual_leave_quota']) ? null : (int) $data['annual_leave_quota']) : $existingJob->annual_leave_quota,
            probation_end_date: array_key_exists('probation_end_date', $data) ? $data['probation_end_date'] : ($existingJob->probation_end_date ? $existingJob->probation_end_date->format('Y-m-d') : null),
            contract_end_date: array_key_exists('contract_end_date', $data) ? $data['contract_end_date'] : ($existingJob->contract_end_date ? $existingJob->contract_end_date->format('Y-m-d') : null),
        );
    }
}
