<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the AI moderator makes of a post (spec §4.6).
 */
enum ModerationVerdict: string
{
    case Allow = 'allow';
    case Review = 'review';
    case Block = 'block';
}
