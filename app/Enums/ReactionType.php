<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The reactions a member can add to someone else's post. Each one is a
 * separate toggle, so a post can be both "insightful" and "love".
 */
enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Laugh = 'laugh';
    case Insightful = 'insightful';
    case Celebrate = 'celebrate';

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Laugh => '😂',
            self::Insightful => '💡',
            self::Celebrate => '🎉',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Like => __('Like'),
            self::Love => __('Love'),
            self::Laugh => __('Funny'),
            self::Insightful => __('Insightful'),
            self::Celebrate => __('Celebrate'),
        };
    }
}
