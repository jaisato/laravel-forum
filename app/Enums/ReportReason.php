<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a post was reported. The AI moderator maps its own categories onto
 * these, so the staff queue speaks a single vocabulary.
 */
enum ReportReason: string implements HasLabel
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case OffTopic = 'off_topic';
    case Illegal = 'illegal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => __('Spam'),
            self::Abuse => __('Abuse or harassment'),
            self::OffTopic => __('Off topic'),
            self::Illegal => __('Illegal content'),
            self::Other => __('Other'),
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
