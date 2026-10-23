<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Moderator => __('Moderator'),
            self::Member => __('Member'),
        };
    }

    /**
     * Staff can moderate content; only administrators manage the forum.
     */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }
}
