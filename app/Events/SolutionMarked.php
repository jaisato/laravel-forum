<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SolutionMarked implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Thread $thread,
        public readonly Post $post,
        public readonly User $actor,
    ) {}
}
