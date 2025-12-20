<?php

namespace Database\Seeders;

use App\Enums\Department;
use App\Enums\TeamStatus;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Team::create([
            'name' => 'Backend Engineering',
            'expected_size' => 8,
            'description' => 'Responsible for server-side logic, API development, and database management. This team ensures our backend infrastructure is scalable, secure, and performant.',
            'icon' => 'team-icons/activity.png',
            'department' => Department::DEVELOPMENT,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Develop and maintain RESTful APIs',
                'Optimize database queries and performance',
                'Implement security best practices',
                'Write comprehensive unit tests',
                'Code review and mentoring',
            ],
        ]);

        Team::create([
            'name' => 'Frontend Engineering',
            'expected_size' => 6,
            'description' => 'Building beautiful and responsive user interfaces using modern frameworks. Focused on creating exceptional user experiences.',
            'icon' => 'team-icons/airplay.png',
            'department' => Department::DEVELOPMENT,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Build responsive web applications',
                'Implement UI/UX designs',
                'Optimize frontend performance',
                'Ensure cross-browser compatibility',
                'Write clean and reusable components',
            ],
        ]);

        Team::create([
            'name' => 'Mobile Development',
            'expected_size' => 5,
            'description' => 'Creating native and cross-platform mobile applications for iOS and Android.',
            'icon' => 'team-icons/activity.png',
            'department' => Department::DEVELOPMENT,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Develop mobile applications',
                'Optimize app performance',
                'Implement push notifications',
                'Handle offline functionality',
                'App store deployment',
            ],
        ]);

        Team::create([
            'name' => 'Product Design',
            'expected_size' => 5,
            'description' => 'Crafting user-centered designs that solve real problems. This team conducts research, creates prototypes, and ensures design consistency.',
            'icon' => 'team-icons/pen-tool.png',
            'department' => Department::DESIGN,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Conduct user research and testing',
                'Create wireframes and prototypes',
                'Design user interfaces',
                'Maintain design system',
                'Collaborate with developers',
            ],
        ]);

        Team::create([
            'name' => 'Brand & Creative',
            'expected_size' => 4,
            'description' => 'Maintaining brand identity and creating visual content for marketing campaigns.',
            'icon' => 'team-icons/coffee.png',
            'department' => Department::DESIGN,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Develop brand guidelines',
                'Create marketing materials',
                'Design social media content',
                'Produce video content',
                'Maintain brand consistency',
            ],
        ]);

        Team::create([
            'name' => 'Growth Marketing',
            'expected_size' => 6,
            'description' => 'Data-driven team focused on user acquisition, retention, and revenue growth through various marketing channels.',
            'icon' => 'team-icons/chart-pie.png',
            'department' => Department::MARKETING,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Develop growth strategies',
                'Run A/B tests',
                'Analyze marketing metrics',
                'Optimize conversion funnels',
                'Manage paid advertising campaigns',
            ],
        ]);

        Team::create([
            'name' => 'Content Marketing',
            'expected_size' => 4,
            'description' => 'Creating engaging content that educates and attracts our target audience.',
            'icon' => 'team-icons/video.png',
            'department' => Department::MARKETING,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Create blog posts and articles',
                'Develop content strategy',
                'Manage social media channels',
                'Produce newsletters',
                'SEO optimization',
            ],
        ]);

        Team::create([
            'name' => 'Enterprise Sales',
            'expected_size' => 7,
            'description' => 'Working with large organizations to understand their needs and provide tailored solutions.',
            'icon' => 'team-icons/wallet-minimal.png',
            'department' => Department::SALES,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Identify enterprise opportunities',
                'Conduct product demonstrations',
                'Negotiate contracts',
                'Manage key accounts',
                'Meet quarterly sales targets',
            ],
        ]);

        Team::create([
            'name' => 'Customer Success',
            'expected_size' => 5,
            'description' => 'Ensuring customer satisfaction and driving product adoption.',
            'icon' => 'team-icons/heart-handshake.png',
            'department' => Department::SALES,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Onboard new customers',
                'Conduct training sessions',
                'Monitor customer health scores',
                'Identify upsell opportunities',
                'Reduce churn rate',
            ],
        ]);

        Team::create([
            'name' => 'Customer Support',
            'expected_size' => 10,
            'description' => 'Providing exceptional support to our customers across multiple channels.',
            'icon' => 'team-icons/smile.png',
            'department' => Department::SUPPORT,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Respond to customer inquiries',
                'Troubleshoot technical issues',
                'Maintain knowledge base',
                'Track support metrics',
                'Escalate complex issues',
            ],
        ]);

        Team::create([
            'name' => 'Product Management',
            'expected_size' => 4,
            'description' => 'Defining product strategy and roadmap. This team bridges business, technology, and user experience.',
            'icon' => 'team-icons/airplay.png',
            'department' => Department::MANAGEMENT,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Define product roadmap',
                'Prioritize features',
                'Gather customer feedback',
                'Coordinate cross-functional teams',
                'Track product metrics',
            ],
        ]);

        Team::create([
            'name' => 'Operations',
            'expected_size' => 5,
            'description' => 'Managing internal operations, processes, and ensuring organizational efficiency.',
            'icon' => 'team-icons/key-round.png',
            'department' => Department::MANAGEMENT,
            'status' => TeamStatus::ACTIVE,
            'responsibilities' => [
                'Optimize business processes',
                'Manage vendor relationships',
                'Oversee budgets',
                'Implement operational tools',
                'Monitor KPIs',
            ],
        ]);
    }
}
