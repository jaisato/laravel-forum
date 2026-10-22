<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PostUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $previousMentions  usernames mentioned before the edit, so only new mentions notify
     */
    public function __construct(
        public readonly Post $post,
        public readonly array $previousMentions,
    ) {}
}
