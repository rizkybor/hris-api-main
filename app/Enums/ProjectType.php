<?php

namespace App\Enums;

enum ProjectType: string
{
    case WEB_DEVELOPMENT = 'web_development';
    case MOBILE_APP = 'mobile_app';
    case DESIGN = 'design';
    case MARKETING = 'marketing';
    case RESEARCH = 'research';
    case INFRASTRUCTURE = 'infrastructure';

    public function label(): string
    {
        return match ($this) {
            self::WEB_DEVELOPMENT => 'Web Development',
            self::MOBILE_APP => 'Mobile App',
            self::DESIGN => 'Design',
            self::MARKETING => 'Marketing',
            self::RESEARCH => 'Research',
            self::INFRASTRUCTURE => 'Infrastructure',
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
