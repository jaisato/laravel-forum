<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostModerated;
use App\Events\PostUpdated;
use App\Events\ThreadDeleted;
use App\Events\ThreadRestored;
use App\Events\ThreadUpdated;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps the search index in step when a thread changes in a way its posts
 * inherit: a new title, other tags, another category, or a new visibility
 * (moderation of its opening post, deletion, restoration). Scout only
 * follows changes to each model, and the thread document also carries the
 * body of its opening post.
 */
final class SyncThreadSearchIndex implements ShouldQueue
{
    public function handleThreadUpdated(ThreadUpdated $event): void
    {
        $this->sync($event->thread->id);
    }

    public function handleThreadDeleted(ThreadDeleted $event): void
    {
        $this->sync($event->thread->id);
    }

    public function handleThreadRestored(ThreadRestored $event): void
    {
        $this->sync($event->thread->id);
    }

    public function handlePostModerated(PostModerated $event): void
    {
        if ($event->post->isFirst()) {
            $this->sync($event->post->thread_id);
        }
    }

    /**
     * Editing the opening post changes the thread document, not its posts.
     */
    public function handlePostUpdated(PostUpdated $event): void
    {
        $thread = $event->post->isFirst() ? $this->thread($event->post->thread_id) : null;

        if ($thread !== null) {
            $this->syncThread($thread);
        }
    }

    private function sync(int $threadId): void
    {
        $thread = $this->thread($threadId);

        if ($thread === null) {
            return;
        }

        $this->syncThread($thread);

        $thread->posts()->with('thread.tags')->chunkById(500, function (Collection $posts): void {
            [$public, $hidden] = $posts->partition(fn (Post $post): bool => $post->shouldBeSearchable());

            // What the searchable()/unsearchable() collection macros do.
            (new Post)->queueMakeSearchable($public);
            (new Post)->queueRemoveFromSearch($hidden);
        });
    }

    private function thread(int $threadId): ?Thread
    {
        return Thread::withTrashed()->with(['firstPost', 'tags'])->find($threadId);
    }

    private function syncThread(Thread $thread): void
    {
        $thread->shouldBeSearchable() ? $thread->searchable() : $thread->unsearchable();
    }
}
