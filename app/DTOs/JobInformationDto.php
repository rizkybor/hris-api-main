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
        public readonly float $monthly_salary,
        public readonly string $skill_level,
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
            monthly_salary: (float) $data['monthly_salary'],
            skill_level: $data['skill_level'],
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
            monthly_salary: isset($data['monthly_salary']) ? (float) $data['monthly_salary'] : ($existingJob->monthly_salary ?? 0.0),
            skill_level: $data['skill_level'] ?? $existingJob->skill_level ?? 'beginner',
        );
    }
}
