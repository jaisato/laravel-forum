<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Moderators ban members; administrators ban anyone but administrators.
     * Also used for lifting a ban.
     */
    public function ban(User $actor, User $target): bool
    {
        if (! $actor->canModerate() || $actor->is($target)) {
            return false;
        }

        return $actor->isAdmin()
            ? ! $target->isAdmin()
            : $target->role === Role::Member;
    }

    /**
     * Administrators cannot change their own role, so the forum never loses
     * its last administrator by accident.
     */
    public function changeRole(User $actor, User $target): bool
    {
        return $actor->canAdminister() && $actor->isNot($target);
    }
}
