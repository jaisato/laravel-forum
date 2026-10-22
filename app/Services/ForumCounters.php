<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Enums\ThreadStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;

/**
 * The one definition of the denormalised counters: only visible content
 * counts, that is published threads that are not deleted and their
 * published posts that are not deleted (opening posts included). Actions
 * add (+1) or remove (-1) a contribution inside their own transaction, so
 * deleting and restoring are exact mirrors; forum:recount follows the same
 * rule from scratch.
 */
final class ForumCounters
{
    public function countsThread(Thread $thread): bool
    {
        return $thread->status === ThreadStatus::Published && ! $thread->trashed();
    }

    public function countsReply(Post $post, Thread $thread): bool
    {
        return $this->countsThread($thread)
            && $post->status === PostStatus::Published
            && ! $post->trashed()
            && (int) $thread->first_post_id !== $post->id;
    }

    /**
     * Adds or removes everything a visible thread contributes: the thread
     * itself and all of its visible posts.
     */
    public function applyThread(Thread $thread, int $direction): void
    {
        $postsByAuthor = Post::query()
            ->where('thread_id', $thread->id)
            ->where('status', PostStatus::Published)
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as aggregate')
            ->pluck('aggregate', 'user_id')
            ->map(fn (mixed $count): int => (int) $count);

        Category::query()->whereKey($thread->category_id)->toBase()->incrementEach([
            'threads_count' => $direction,
            'posts_count' => $direction * $postsByAuthor->sum(),
        ]);

        User::withTrashed()->whereKey($thread->user_id)->toBase()->increment('threads_count', $direction);

        foreach ($postsByAuthor as $userId => $count) {
            User::withTrashed()->whereKey($userId)->toBase()->increment('posts_count', $direction * $count);
        }

        $this->applyTags($thread->tags()->pluck('tags.id')->all(), $direction);
    }

    /**
     * Adds or removes what a visible reply contributes.
     */
    public function applyReply(Post $post, Thread $thread, int $direction): void
    {
        Thread::withTrashed()->whereKey($thread->id)->toBase()->increment('replies_count', $direction);
        Category::query()->whereKey($thread->category_id)->toBase()->increment('posts_count', $direction);
        User::withTrashed()->whereKey($post->user_id)->toBase()->increment('posts_count', $direction);
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    public function applyTags(array $tagIds, int $direction): void
    {
        if ($tagIds !== []) {
            Tag::query()->whereKey($tagIds)->toBase()->increment('threads_count', $direction);
        }
    }

    /**
     * Points the thread at its newest visible post, which is at least its
     * opening post while the thread is visible.
     */
    public function refreshLastPost(Thread $thread): void
    {
        $last = Post::query()
            ->where('thread_id', $thread->id)
            ->where('status', PostStatus::Published)
            ->latest('id')
            ->first(['id', 'created_at']);

        $thread->forceFill([
            'last_post_id' => $last?->id,
            'last_posted_at' => $last?->created_at ?? $thread->created_at,
        ])->save();
    }
}
