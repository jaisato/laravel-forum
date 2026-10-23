<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who raised a report: a member, or the AI moderator (which has no
 * reporter account and records its confidence instead).
 */
enum ReportSource: string
{
    case User = 'user';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::User => __('Member'),
            self::Ai => __('AI'),
        };
    }
}
