<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\TeamStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $department = fake()->randomElement(Department::cases());

        return [
            'name' => $this->generateTeamName($department),
            'expected_size' => fake()->numberBetween(3, 15),
            'description' => fake()->paragraph(2),
            'icon' => $this->getIconForDepartment($department),
            'department' => $department,
            'status' => fake()->randomElement(TeamStatus::cases()),
            'team_lead_id' => User::factory(),
            'responsibilities' => $this->getResponsibilitiesForDepartment($department),
        ];
    }

    /**
     * Generate appropriate team name based on department
     */
    private function generateTeamName(Department $department): string
    {
        $teamNames = [
            Department::DEVELOPMENT->value => [
                'Backend Team',
                'Frontend Team',
                'Mobile Development',
                'DevOps Team',
                'Full Stack Team',
                'Infrastructure Team',
                'API Development',
                'Platform Engineering',
            ],
            Department::DESIGN->value => [
                'UI/UX Design Team',
                'Product Design',
                'Visual Design',
                'Brand Design',
                'Design Systems',
                'Creative Team',
                'Graphic Design',
            ],
            Department::MARKETING->value => [
                'Digital Marketing',
                'Content Marketing',
                'Growth Team',
                'Brand Marketing',
                'Marketing Analytics',
                'Social Media Team',
                'SEO Team',
            ],
            Department::SALES->value => [
                'Enterprise Sales',
                'Inside Sales',
                'Sales Development',
                'Account Management',
                'Business Development',
                'Customer Success',
            ],
            Department::SUPPORT->value => [
                'Customer Support',
                'Technical Support',
                'Help Desk Team',
                'Client Services',
                'Support Operations',
            ],
            Department::MANAGEMENT->value => [
                'Executive Team',
                'Operations Management',
                'Project Management',
                'Product Management',
                'Strategic Planning',
            ],
        ];

        return fake()->randomElement($teamNames[$department->value]);
    }

    /**
     * Get appropriate icon for department
     * Returns storage path (relative to storage/app/public)
     */
    private function getIconForDepartment(Department $department): string
    {
        return match ($department) {
            Department::DEVELOPMENT => fake()->randomElement([
                'team-icons/activity.png',
                'team-icons/airplay.png',
            ]),
            Department::DESIGN => fake()->randomElement([
                'team-icons/pen-tool.png',
                'team-icons/coffee.png',
            ]),
            Department::MARKETING => fake()->randomElement([
                'team-icons/chart-pie.png',
                'team-icons/video.png',
            ]),
            Department::SALES => fake()->randomElement([
                'team-icons/phone.png',
                'team-icons/wallet-minimal.png',
            ]),
            Department::SUPPORT => fake()->randomElement([
                'team-icons/smile.png',
                'team-icons/heart-handshake.png',
            ]),
            Department::MANAGEMENT => fake()->randomElement([
                'team-icons/airplay.png',
                'team-icons/key-round.png',
            ]),
        };
    }

    /**
     * Get responsibilities based on department
     */
    private function getResponsibilitiesForDepartment(Department $department): array
    {
        $responsibilities = [
            Department::DEVELOPMENT->value => [
                'Write clean and maintainable code',
                'Participate in code reviews',
                'Debug and fix technical issues',
                'Implement new features',
                'Optimize application performance',
                'Write technical documentation',
            ],
            Department::DESIGN->value => [
                'Create user-centered designs',
                'Develop design systems',
                'Conduct user research',
                'Create prototypes and mockups',
                'Collaborate with development team',
                'Maintain brand consistency',
            ],
            Department::MARKETING->value => [
                'Develop marketing strategies',
                'Create engaging content',
                'Analyze campaign performance',
                'Manage social media presence',
                'Generate leads',
                'Brand positioning',
            ],
            Department::SALES->value => [
                'Generate new business opportunities',
                'Maintain client relationships',
                'Meet sales targets',
                'Conduct product demonstrations',
                'Negotiate contracts',
                'Pipeline management',
            ],
            Department::SUPPORT->value => [
                'Respond to customer inquiries',
                'Resolve technical issues',
                'Maintain knowledge base',
                'Escalate complex cases',
                'Track customer satisfaction',
                'Provide product training',
            ],
            Department::MANAGEMENT->value => [
                'Set strategic direction',
                'Manage team performance',
                'Budget planning and oversight',
                'Stakeholder communication',
                'Risk management',
                'Process improvement',
            ],
        ];

        $deptResponsibilities = $responsibilities[$department->value];

        return fake()->randomElements($deptResponsibilities, fake()->numberBetween(3, 5));
    }

    /**
     * Indicate that the team is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TeamStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the team is forming.
     */
    public function forming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TeamStatus::FORMING,
        ]);
    }

    /**
     * Indicate that the team has a specific department.
     */
    public function department(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => $department,
            'name' => $this->generateTeamName($department),
            'icon' => $this->getIconForDepartment($department),
            'responsibilities' => $this->getResponsibilitiesForDepartment($department),
        ]);
    }
}
