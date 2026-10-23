<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\ForumAi;
use App\Events\PostCreated;
use App\Events\PostUpdated;
use App\Events\ThreadUpdated;
use App\Jobs\Ai\ModeratePost;
use App\Models\Post;

/**
 * Sends every new or edited post to the AI moderator, in the queue, while
 * the feature is on, and the opening post again when a member gives the
 * thread a new title (the moderator reads both). Staff posts and titles
 * are not checked.
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

    public function handleThreadUpdated(ThreadUpdated $event): void
    {
        if (! $event->titleChanged || $event->editor?->isStaff() !== false) {
            return;
        }

        $opening = $event->thread->loadMissing('firstPost')->firstPost;

        if ($opening !== null) {
            $this->queue($opening);
        }
    }

    private function queue(Post $post): void
    {
        if (! $this->ai->enabled('moderation') || $post->loadMissing('author')->author?->isStaff() === true) {
            return;
        }

        ModeratePost::dispatch($post);
    }
}
