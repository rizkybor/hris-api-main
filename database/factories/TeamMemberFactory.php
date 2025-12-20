<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'employee_id' => EmployeeProfile::factory(),
            'joined_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'left_at' => null,
        ];
    }

    /**
     * Indicate that the team member has left the team.
     */
    public function left(): static
    {
        return $this->state(function (array $attributes) {
            $joinedAt = $attributes['joined_at'];

            return [
                'left_at' => fake()->dateTimeBetween($joinedAt, 'now'),
            ];
        });
    }

    /**
     * Indicate that the team member is still active (no left_at date).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'left_at' => null,
        ]);
    }

    /**
     * Indicate that the team member recently joined.
     */
    public function recentlyJoined(): static
    {
        return $this->state(fn (array $attributes) => [
            'joined_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'left_at' => null,
        ]);
    }

    /**
     * Assign to specific team
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Assign specific employee
     */
    public function forEmployee(EmployeeProfile $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
