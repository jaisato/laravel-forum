<?php

declare(strict_types=1);

namespace App\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The member of the staff using the panel: the moderation actions are taken
 * (and logged) in their name.
 */
final class Staff
{
    public static function current(): User
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
