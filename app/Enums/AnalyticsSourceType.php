<?php

namespace App\Enums;

enum AnalyticsSourceType: string
{
    case POSTHOG = 'posthog';
    case GOOGLE_ANALYTICS_4 = 'google_analytics_4';
    case GOOGLE_SEARCH_CONSOLE = 'google_search_console';

    public function label(): string
    {
        return match ($this) {
            self::POSTHOG => 'PostHog',
            self::GOOGLE_ANALYTICS_4 => 'Google Analytics 4',
            self::GOOGLE_SEARCH_CONSOLE => 'Google Search Console',
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}
