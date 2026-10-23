<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Moderation state of a thread. Pending threads are visible to their author
 * and to staff; hidden ones only to staff.
 */
enum ThreadStatus: string implements HasLabel
{
    case Published = 'published';
    case PendingReview = 'pending_review';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Published => __('Published'),
            self::PendingReview => __('Pending review'),
            self::Hidden => __('Hidden'),
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
