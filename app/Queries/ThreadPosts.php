<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The posts of a thread as a viewer reads them, on the thread page and in
 * the API: those they may see (staff also deleted ones, to restore them),
 * oldest first, loaded as PostResource needs them.
 */
final class ThreadPosts
{
    /**
     * @return HasMany<Post, Thread>
     */
    public function visible(Thread $thread, ?User $viewer): HasMany
    {
        $posts = $thread->posts()->visibleTo($viewer);

        return $viewer?->canModerate() ? $posts->withTrashed() : $posts;
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginate(Thread $thread, ?User $viewer, ?int $page = null): LengthAwarePaginator
    {
        $posts = $this->visible($thread, $viewer)
            ->with(['author', 'replyTo.author'])
            ->withReactionSummary($viewer)
            ->orderBy('id')
            ->paginate((int) config('forum.pagination.posts'), page: $page);

        // The policies and the resource read the thread of every post.
        $posts->getCollection()->each->setRelation('thread', $thread);

        return $posts;
    }

    /**
     * One post, loaded as paginate() loads them.
     */
    public function one(Post $post, ?User $viewer): Post
    {
        $loaded = Post::withTrashed()
            ->whereKey($post->id)
            ->with(['author', 'replyTo.author'])
            ->withReactionSummary($viewer)
            ->firstOrFail();

        // Deleted threads too: staff read their posts, read-only.
        return $loaded->setRelation('thread', Thread::withTrashed()->findOrFail($loaded->thread_id));
    }
}
