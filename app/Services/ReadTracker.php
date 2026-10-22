<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\Thread;
use App\Models\ThreadRead;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Per member read state of threads, kept as a post id watermark.
 */
final class ReadTracker
{
    /**
     * Marks the thread as read up to its newest post.
     */
    public function markRead(Thread $thread, User $user): void
    {
        ThreadRead::query()->upsert(
            [
                'thread_id' => $thread->id,
                'user_id' => $user->id,
                'last_read_post_id' => (int) $thread->last_post_id,
                'last_read_at' => now(),
            ],
            uniqueBy: ['thread_id', 'user_id'],
            update: ['last_read_post_id', 'last_read_at'],
        );
    }

    public function lastReadAt(Thread $thread, User $user): ?CarbonInterface
    {
        return $this->marker($thread, $user)?->last_read_at;
    }

    /**
     * The first post the member has not read yet among those they may see:
     * the opening post for a thread they never read, null when all is read.
     */
    public function firstUnreadPost(Thread $thread, User $user): ?Post
    {
        return $thread->posts()
            ->visibleTo($user)
            ->where('id', '>', $this->marker($thread, $user)->last_read_post_id ?? 0)
            ->orderBy('id')
            ->first();
    }

    private function marker(Thread $thread, User $user): ?ThreadRead
    {
        return ThreadRead::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
