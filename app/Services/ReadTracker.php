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
     * Marks the thread as read up to its newest post, or up to the given
     * post (the last one of the page the member is reading). The marker only
     * moves forward: a request that loaded the thread earlier may finish after
     * one that read further (the member's own reply, another tab).
     */
    public function markRead(Thread $thread, User $user, ?int $upToPostId = null): void
    {
        $lastPostId = $upToPostId ?? (int) $thread->last_post_id;
        $marker = [
            'last_read_post_id' => $lastPostId,
            'last_read_at' => now(),
        ];

        ThreadRead::query()->insertOrIgnore(['thread_id' => $thread->id, 'user_id' => $user->id, ...$marker]);

        ThreadRead::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->where('last_read_post_id', '<=', $lastPostId)
            ->update($marker);
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
