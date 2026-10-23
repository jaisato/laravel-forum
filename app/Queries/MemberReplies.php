<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The replies of a member, newest first, as their profile lists them (web
 * and API): what they wrote in any thread except the posts that open their
 * own threads, which the profile lists as threads. Viewers only get the
 * posts they may see, in threads they may see.
 */
final class MemberReplies
{
    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginate(User $member, ?User $viewer): LengthAwarePaginator
    {
        return Post::query()
            ->where('posts.user_id', $member->id)
            ->visibleTo($viewer)
            ->whereHas('thread', function (Builder $threads) use ($viewer): Builder {
                /** @var Builder<Thread> $threads */
                return $threads->visibleTo($viewer)->whereColumn('threads.first_post_id', '<>', 'posts.id');
            })
            ->with(['author', 'thread'])
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->paginate((int) config('forum.pagination.posts'));
    }
}
