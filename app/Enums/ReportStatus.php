<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a report in the staff queue. Only open reports block a new
 * report of the same post by the same member.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Resolved => __('Resolved'),
            self::Dismissed => __('Dismissed'),
        };
    }
}
