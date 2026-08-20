<?php

namespace App\Enums;

/**
 * Fixed swatch palette for a task's Type badge/Task Detail accent color --
 * a palette *key*, not a raw hex value, so the frontend maps it to a fixed
 * Tailwind class pair the same way every other badge color in this app
 * works (see src/utils/styleHelpers.js, which is the source of truth for
 * the actual class pairs -- this enum only needs to keep its case list in
 * sync with that file's keys for validation).
 */
enum TaskColor: string
{
    case BLUE = 'blue';
    case EMERALD = 'emerald';
    case AMBER = 'amber';
    case ROSE = 'rose';
    case VIOLET = 'violet';
    case CYAN = 'cyan';
    case ORANGE = 'orange';
    case PINK = 'pink';
    case SLATE = 'slate';
    case INDIGO = 'indigo';
    case TEAL = 'teal';
    case FUCHSIA = 'fuchsia';
}
