<?php

namespace Database\Seeders;

use App\Models\FilesCompany;
use Illuminate\Database\Seeder;

class FilesCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FilesCompany::create([
            'path' => 'company-files/policies/employee-handbook.pdf',
            'name' => 'Employee Handbook 2024',
            'description' => 'Complete employee handbook with company policies, code of conduct, and procedures',
        ]);

        FilesCompany::create([
            'path' => 'company-files/policies/health-safety.pdf',
            'name' => 'Health & Safety Policy',
            'description' => 'Workplace health and safety guidelines and emergency procedures',
        ]);

        FilesCompany::create([
            'path' => 'company-files/contracts/template-employment.pdf',
            'name' => 'Employment Contract Template',
            'description' => 'Standard employment contract template for new hires',
        ]);

        FilesCompany::create([
            'path' => 'company-files/contracts/template-nda.pdf',
            'name' => 'NDA Template',
            'description' => 'Non-disclosure agreement template for contractors and partners',
        ]);

        FilesCompany::create([
            'path' => 'company-files/legal/articles-of-incorporation.pdf',
            'name' => 'Articles of Incorporation',
            'description' => 'Official company registration documents',
        ]);

        FilesCompany::create([
            'path' => 'company-files/legal/business-license.pdf',
            'name' => 'Business License',
            'description' => 'Current business operating license',
        ]);

        FilesCompany::create([
            'path' => 'company-files/financial/annual-report-2023.pdf',
            'name' => 'Annual Report 2023',
            'description' => 'Company annual financial report and performance summary',
        ]);

        FilesCompany::create([
            'path' => 'company-files/financial/tax-documents.pdf',
            'name' => 'Tax Documents',
            'description' => 'Corporate tax filing documents and receipts',
        ]);

        FilesCompany::create([
            'path' => 'company-files/marketing/brand-guidelines.pdf',
            'name' => 'Brand Guidelines',
            'description' => 'Company branding guidelines, logo usage, and style guide',
        ]);

        FilesCompany::create([
            'path' => 'company-files/marketing/presentation-template.pptx',
            'name' => 'Company Presentation Template',
            'description' => 'Standard PowerPoint template for company presentations',
        ]);

        FilesCompany::create([
            'path' => 'company-files/hr/onboarding-checklist.pdf',
            'name' => 'Employee Onboarding Checklist',
            'description' => 'Step-by-step checklist for new employee onboarding process',
        ]);

        FilesCompany::create([
            'path' => 'company-files/hr/performance-review-form.pdf',
            'name' => 'Performance Review Form',
            'description' => 'Standard form for employee performance evaluations',
        ]);

        FilesCompany::create([
            'path' => 'company-files/it/network-diagram.pdf',
            'name' => 'Network Infrastructure Diagram',
            'description' => 'Company network architecture and infrastructure documentation',
        ]);

        FilesCompany::create([
            'path' => 'company-files/it/security-policy.pdf',
            'name' => 'IT Security Policy',
            'description' => 'Information technology security policies and procedures',
        ]);

        FilesCompany::create([
            'path' => 'company-files/operations/standard-operating-procedures.pdf',
            'name' => 'Standard Operating Procedures',
            'description' => 'SOPs for various business operations and workflows',
        ]);
    }
}

