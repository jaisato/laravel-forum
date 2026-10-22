<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ReactionType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReactionAdded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly User $user,
        public readonly ReactionType $type,
    ) {}
}
