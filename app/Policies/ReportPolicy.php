<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canModerate();
    }

    public function view(User $user, Report $report): bool
    {
        return $user->canModerate();
    }

    public function resolve(User $user, Report $report): bool
    {
        return $user->canModerate();
    }
}
