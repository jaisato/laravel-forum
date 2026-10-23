<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\ForumAi;
use App\Events\PostCreated;
use App\Events\PostUpdated;
use App\Jobs\Ai\ModeratePost;
use App\Models\Post;

/**
 * Sends every new or edited post to the AI moderator, in the queue, while
 * the feature is on. Staff posts are not checked.
 */
final class QueueAiModeration
{
    public function __construct(private readonly ForumAi $ai) {}

    public function handlePostCreated(PostCreated $event): void
    {
        $this->queue($event->post);
    }

    public function handlePostUpdated(PostUpdated $event): void
    {
        $this->queue($event->post);
    }

    private function queue(Post $post): void
    {
        if (! $this->ai->enabled('moderation') || $post->loadMissing('author')->author?->isStaff() === true) {
            return;
        }

        ModeratePost::dispatch($post);
    }
}
