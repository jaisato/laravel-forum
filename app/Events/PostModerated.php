<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A post changed its moderation status: held for review, hidden or
 * approved. A null moderator means the system acted (AI moderation).
 */
final class PostModerated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly ?User $moderator,
        public readonly PostStatus $status,
        public readonly ?string $reason = null,
    ) {}
}
