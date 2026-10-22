<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Moderation state of a post. Pending posts are visible to their author and
 * to staff; hidden ones only to staff.
 */
enum PostStatus: string
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
}
