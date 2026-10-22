<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Thread;
use App\Models\ThreadRead;
use App\Models\User;

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
}
