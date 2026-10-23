<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ModerationAction;
use App\Models\User;

/**
 * The moderation log is read-only: entries are written by the moderation
 * actions only, so no ability allows changing or deleting them.
 */
class ModerationActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canModerate();
    }

    public function view(User $user, ModerationAction $action): bool
    {
        return $user->canModerate();
    }
}
