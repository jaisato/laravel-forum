<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PostCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $mentions  usernames the post mentioned when it was created
     */
    public function __construct(
        public readonly Post $post,
        public readonly array $mentions,
    ) {}
}
