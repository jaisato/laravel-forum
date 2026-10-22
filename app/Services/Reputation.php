<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;

/**
 * Reputation earned from the community: each reaction received (+1) and
 * each accepted answer (+15). Amounts come from config('forum.reputation').
 */
final class Reputation
{
    public function reaction(Post $post, int $direction): void
    {
        $this->adjust($post->user_id, $direction * (int) config('forum.reputation.reaction'));
    }

    /**
     * Answering your own question earns nothing, so the bonus cannot be
     * farmed with self-answered threads.
     */
    public function solution(Thread $thread, Post $post, int $direction): void
    {
        if ((int) $post->user_id === (int) $thread->user_id) {
            return;
        }

        $this->adjust($post->user_id, $direction * (int) config('forum.reputation.solution'));
    }

    private function adjust(int $userId, int $amount): void
    {
        User::withTrashed()->whereKey($userId)->toBase()->increment('reputation', $amount);
    }
}
