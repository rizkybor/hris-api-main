<?php

namespace App\Interfaces;

interface OptionRepositoryInterface
{
    /**
     * Get all department options.
     */
    public function getDepartmentOptions(): array;

    public function getEmploymentTypeOptions(): array;

    public function getJobStatusOptions(): array;

    public function getTaskPriorityOptions(): array;

    public function getTaskStatusOptions(): array;

    public function getLeaveTypeOptions(): array;

    public function getWorkLocationOptions(): array;

    public function getSkillLevelOptions(): array;
}
