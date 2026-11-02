<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL'),
        'token' => env('WHATSAPP_TOKEN'),
        'delay' => env('WHATSAPP_DELAY', 0),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    // Client Screening: the public Notion database the service catalog is
    // synced from, where the landing page form lives (invite links point
    // there), and who is emailed when a client submits.
    'rate_card' => [
        'notion_site' => env('RATE_CARD_NOTION_SITE', 'https://viridian-tv-cc3.notion.site'),
        'space_id' => env('RATE_CARD_SPACE_ID', 'e37c6d2f-09d1-8126-87ab-0003467dfea3'),
        'collection_id' => env('RATE_CARD_COLLECTION_ID', '360c6d2f-09d1-81fc-9f22-000b719ab543'),
        'view_id' => env('RATE_CARD_VIEW_ID', '360c6d2f-09d1-8183-b179-000cfd7bc864'),
    ],

    'screening' => [
        'form_url' => env('SCREENING_FORM_URL', 'https://jcdigital.co.id/konsultasi-kebutuhan'),
        'form_url_en' => env('SCREENING_FORM_URL_EN', 'https://jcdigital.co.id/en/requirements-check'),
        'notify_email' => env('SCREENING_NOTIFY_EMAIL', 'contact@jcdigital.co.id'),
        'invite_days' => (int) env('SCREENING_INVITE_DAYS', 30),
    ],

    // Daily email listing each active subscription whose next due date is
    // coming up (see subscriptions:send-due-reminders).
    // Replies to a proposal shared by email go to the company inbox.
    'proposal_share' => [
        'reply_to' => env('PROPOSAL_SHARE_REPLY_TO', 'contact@jcdigital.co.id'),
    ],

    'subscription_reminder' => [
        'recipient' => env('SUBSCRIPTION_REMINDER_EMAIL', 'contact@jcdigital.co.id'),
        'days_before' => (int) env('SUBSCRIPTION_REMINDER_DAYS', 30),
    ],

];
