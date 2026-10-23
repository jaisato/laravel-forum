<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of a report in the staff queue. Only open reports block a new
 * report of the same post by the same member.
 */
enum ReportStatus: string implements HasLabel
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

    /**
     * The label the admin panel (Filament) shows: the same as everywhere else.
     */
    public function getLabel(): string
    {
        return $this->label();
    }
}
