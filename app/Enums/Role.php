<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
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
     * The label the admin panel (Filament) shows: the same as everywhere else.
     */
    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Staff can moderate content; only administrators manage the forum.
     */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }
}
