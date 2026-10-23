<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Thread;
use App\Models\ThreadSubscription;
use App\Models\User;

/**
 * Following threads. Subscribing is idempotent: a repeated or concurrent
 * request keeps a single row thanks to the unique index.
 */
final class ThreadSubscriptions
{
    public function subscribe(Thread $thread, User $user): void
    {
        $now = now();

        ThreadSubscription::query()->insertOrIgnore([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function unsubscribe(Thread $thread, User $user): void
    {
        ThreadSubscription::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    public function isSubscribed(Thread $thread, User $user): bool
    {
        return ThreadSubscription::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
