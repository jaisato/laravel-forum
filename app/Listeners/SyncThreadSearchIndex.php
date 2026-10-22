<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostModerated;
use App\Events\ThreadUpdated;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps the search index in step when a thread changes in a way its posts
 * inherit: a new title, other tags, another category, or a new visibility
 * through its opening post. Scout only follows changes to each model.
 */
final class SyncThreadSearchIndex implements ShouldQueue
{
    public function handleThreadUpdated(ThreadUpdated $event): void
    {
        $this->sync($event->thread->id);
    }

    public function handlePostModerated(PostModerated $event): void
    {
        if ($event->post->isFirst()) {
            $this->sync($event->post->thread_id);
        }
    }

    private function sync(int $threadId): void
    {
        $thread = Thread::withTrashed()->with(['firstPost', 'tags'])->find($threadId);

        if ($thread === null) {
            return;
        }

        $thread->shouldBeSearchable() ? $thread->searchable() : $thread->unsearchable();

        $thread->posts()->with('thread.tags')->chunkById(500, function (Collection $posts): void {
            [$public, $hidden] = $posts->partition(fn (Post $post): bool => $post->shouldBeSearchable());

            $public->searchable();
            $hidden->unsearchable();
        });
    }
}
