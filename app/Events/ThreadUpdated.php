<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ThreadUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $titleChanged  whether the title is new (the AI moderates it with the opening post)
     * @param  User|null  $editor  who changed it, when a member or the staff did
     */
    public function __construct(
        public readonly Thread $thread,
        public readonly bool $titleChanged = false,
        public readonly ?User $editor = null,
    ) {}
}
