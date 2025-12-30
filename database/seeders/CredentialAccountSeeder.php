<?php

namespace Database\Seeders;

use App\Models\CredentialAccount;
use Illuminate\Database\Seeder;

class CredentialAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CredentialAccount::create([
            'label_password' => 'GitHub Account',
            'username_email' => 'developer@company.com',
            'password' => 'SecurePassword123!',
            'website' => 'https://github.com',
            'notes' => 'Main development account for version control',
        ]);

        CredentialAccount::create([
            'label_password' => 'AWS Console',
            'username_email' => 'admin@company.com',
            'password' => 'AwsSecureKey456@',
            'website' => 'https://console.aws.amazon.com',
            'notes' => 'Production AWS account - handle with care',
        ]);

        CredentialAccount::create([
            'label_password' => 'Database Admin',
            'username_email' => 'dbadmin@company.com',
            'password' => 'DbAdmin789!',
            'website' => 'https://db.company.com',
            'notes' => 'PostgreSQL admin credentials',
        ]);

        CredentialAccount::create([
            'label_password' => 'Email Service',
            'username_email' => 'support@company.com',
            'password' => 'EmailPass2024!',
            'website' => 'https://mail.company.com',
            'notes' => 'Company email service account',
        ]);

        CredentialAccount::create([
            'label_password' => 'Project Management Tool',
            'username_email' => 'pm@company.com',
            'password' => 'PmToolPass321!',
            'website' => 'https://pm.company.com',
            'notes' => 'Jira/Asana project management account',
        ]);

        CredentialAccount::create([
            'label_password' => 'Cloud Storage',
            'username_email' => 'storage@company.com',
            'password' => 'StorageKey654!',
            'website' => 'https://storage.company.com',
            'notes' => 'Google Drive/Dropbox business account',
        ]);

        CredentialAccount::create([
            'label_password' => 'Payment Gateway',
            'username_email' => 'payment@company.com',
            'password' => 'PaymentSecure987!',
            'website' => 'https://payment.company.com',
            'notes' => 'Stripe/PayPal merchant account',
        ]);

        CredentialAccount::create([
            'label_password' => 'Social Media Manager',
            'username_email' => 'social@company.com',
            'password' => 'SocialMedia2024!',
            'website' => 'https://social.company.com',
            'notes' => 'Hootsuite/Buffer social media management',
        ]);

        CredentialAccount::create([
            'label_password' => 'CRM System',
            'username_email' => 'crm@company.com',
            'password' => 'CrmAccess123!',
            'website' => 'https://crm.company.com',
            'notes' => 'Salesforce/HubSpot CRM credentials',
        ]);

        CredentialAccount::create([
            'label_password' => 'Analytics Dashboard',
            'username_email' => 'analytics@company.com',
            'password' => 'AnalyticsPass456!',
            'website' => 'https://analytics.company.com',
            'notes' => 'Google Analytics/Data Studio access',
        ]);
    }
}

