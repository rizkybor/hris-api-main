<?php

namespace App\Repositories;

use App\Enums\Department;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\LeaveType;
use App\Enums\SkillLevel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkLocation;
use App\Interfaces\OptionRepositoryInterface;

class OptionRepository implements OptionRepositoryInterface
{
    /**
     * Get all department options
     */
    public function getDepartmentOptions(): array
    {
        return $this->mapEnumToOptions(Department::cases());
    }

    public function getEmploymentTypeOptions(): array
    {
        return $this->mapEnumToOptions(EmploymentType::cases());
    }

    public function getJobStatusOptions(): array
    {
        return $this->mapEnumToOptions(JobStatus::cases());
    }

    public function getTaskPriorityOptions(): array
    {
        return $this->mapEnumToOptions(TaskPriority::cases());
    }

    public function getTaskStatusOptions(): array
    {
        return $this->mapEnumToOptions(TaskStatus::cases());
    }

    public function getLeaveTypeOptions(): array
    {
        return $this->mapEnumToOptions(LeaveType::cases());
    }

    public function getWorkLocationOptions(): array
    {
        return $this->mapEnumToOptions(WorkLocation::cases());
    }

    public function getSkillLevelOptions(): array
    {
        return $this->mapEnumToOptions(SkillLevel::cases());
    }

    /**
     * Map enum cases to options array with value and label
     */
    private function mapEnumToOptions(array $cases): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }
}
