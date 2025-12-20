<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobInformation>
 */
class JobInformationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jobTitles = [
            'Software Engineer',
            'Senior Software Engineer',
            'Backend Developer',
            'Frontend Developer',
            'Full Stack Developer',
            'Mobile Developer',
            'DevOps Engineer',
            'UI/UX Designer',
            'Product Designer',
            'Graphic Designer',
            'Marketing Manager',
            'Content Writer',
            'SEO Specialist',
            'Digital Marketing Specialist',
            'Sales Executive',
            'Account Manager',
            'Business Development Manager',
            'Customer Support Specialist',
            'Technical Support Engineer',
            'Product Manager',
            'Project Manager',
            'HR Manager',
            'Operations Manager',
        ];

        $statuses = ['active', 'inactive', 'on_leave', 'probation'];
        $employmentTypes = ['full_time', 'part_time', 'contract', 'internship', 'freelance'];
        $workLocations = ['office', 'remote', 'hybrid'];

        $yearsExperience = fake()->numberBetween(0, 15);
        $startDate = fake()->dateTimeBetween('-5 years', 'now');

        // Calculate salary based on experience
        $baseSalary = 5000000; // 5 million IDR
        $salaryMultiplier = 1 + ($yearsExperience * 0.15);
        $monthlySalary = $baseSalary * $salaryMultiplier * fake()->randomFloat(2, 0.9, 1.3);

        return [
            'employee_id' => EmployeeProfile::factory(),
            'job_title' => fake()->randomElement($jobTitles),
            'team_id' => null, // Will be assigned when team is created
            'years_experience' => $yearsExperience,
            'status' => fake()->randomElement($statuses),
            'employment_type' => fake()->randomElement($employmentTypes),
            'work_location' => fake()->randomElement($workLocations),
            'start_date' => $startDate,
            'monthly_salary' => round($monthlySalary, 2),
            'skill_level' => $this->getSkillLevelByExperience($yearsExperience),
        ];
    }

    /**
     * Get skill level based on years of experience
     */
    private function getSkillLevelByExperience(int $years): string
    {
        if ($years < 2) {
            return 'beginner';
        }
        if ($years < 5) {
            return 'intermediate';
        }
        if ($years < 8) {
            return 'advanced';
        }

        return 'expert';
    }

    /**
     * Indicate that the employee is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the employee is on probation.
     */
    public function probation(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'probation',
            'start_date' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    /**
     * Indicate that the employee works full time.
     */
    public function fullTime(): static
    {
        return $this->state(fn(array $attributes) => [
            'employment_type' => 'full_time',
        ]);
    }

    /**
     * Indicate that the employee works remotely.
     */
    public function remote(): static
    {
        return $this->state(fn(array $attributes) => [
            'work_location' => 'remote',
        ]);
    }

    /**
     * Assign to specific employee
     */
    public function forEmployee(EmployeeProfile $employee): static
    {
        return $this->state(fn(array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Assign to specific team
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn(array $attributes) => [
            'team_id' => $team->id,
        ]);
    }
}
