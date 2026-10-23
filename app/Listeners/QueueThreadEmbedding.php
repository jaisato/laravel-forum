<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\ForumAi;
use App\Events\PostUpdated;
use App\Events\ThreadCreated;
use App\Events\ThreadUpdated;
use App\Jobs\Ai\GenerateThreadEmbedding;
use App\Models\Thread;

/**
 * Keeps a thread's embedding in step with what it embeds, its title and
 * opening post, while similar threads use vector search. Other changes are
 * caught by the job's content hash.
 */
final class QueueThreadEmbedding
{
    public function __construct(private readonly ForumAi $ai) {}

    public function handleThreadCreated(ThreadCreated $event): void
    {
        $this->queue($event->thread);
    }

    public function handleThreadUpdated(ThreadUpdated $event): void
    {
        $this->queue($event->thread);
    }

    public function handlePostUpdated(PostUpdated $event): void
    {
        if (! $this->ai->usesVectorSearch() || ! $event->post->isFirst()) {
            return;
        }

        $thread = Thread::query()->find($event->post->thread_id);

        if ($thread !== null) {
            $this->queue($thread);
        }
    }

    private function queue(Thread $thread): void
    {
        if ($this->ai->usesVectorSearch()) {
            GenerateThreadEmbedding::dispatch($thread);
        }
    }
}
