<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Category;
use App\Models\Post;
use App\Models\Thread;
use App\Models\ThreadSubscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The thread listings (spec §4.2): the global one, a category, a tag and
 * the latest threads of the home page, shared by the web and the API.
 * Viewers only get the threads they may see, with their read marker.
 */
final class ThreadListing
{
    public const string DEFAULT_SORT = 'activity';

    public const string DEFAULT_FILTER = 'all';

    public const array SORTS = ['activity', 'newest', 'replies', 'views'];

    public const array FILTERS = ['all', 'unanswered', 'solved', 'unsolved', 'following', 'mine'];

    /**
     * Filters about the viewer's own activity; guests get every thread.
     */
    public const array PERSONAL_FILTERS = ['following', 'mine'];

    /**
     * Characters of the opening post read for the excerpt: enough Markdown
     * for a short summary without loading whole posts into every listing.
     */
    private const int EXCERPT_SOURCE_LENGTH = 600;

    /**
     * @param  array{sort?: string, filter?: string, category?: string, tag?: string}  $filters
     * @return LengthAwarePaginator<int, Thread>
     */
    public function paginate(array $filters, ?User $viewer): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? self::DEFAULT_SORT;
        $filter = $filters['filter'] ?? self::DEFAULT_FILTER;

        return $this->query($filters, $viewer)
            ->paginate((int) config('forum.pagination.threads'))
            // Only what changes the listing travels in the page links.
            ->appends(array_filter([
                'sort' => $sort === self::DEFAULT_SORT ? null : $sort,
                'filter' => $filter === self::DEFAULT_FILTER ? null : $filter,
            ]));
    }

    /**
     * The threads with the most recent activity.
     *
     * @return Collection<int, Thread>
     */
    public function latest(?User $viewer, int $limit): Collection
    {
        return $this->query([], $viewer)->limit($limit)->get();
    }

    /**
     * @param  array{sort?: string, filter?: string, category?: string, tag?: string}  $filters
     * @return Builder<Thread>
     */
    private function query(array $filters, ?User $viewer): Builder
    {
        $query = Thread::query()
            ->visibleTo($viewer)
            ->withReadState($viewer)
            ->addSelect(['excerpt_source' => Post::query()
                ->selectRaw('substr(body, 1, '.self::EXCERPT_SOURCE_LENGTH.')')
                ->whereColumn('posts.id', 'threads.first_post_id')])
            ->with([
                'author',
                'category',
                'tags' => fn (BelongsToMany $query) => $query->orderBy('tags.name'),
                'lastPost.author',
            ]);

        $category = $filters['category'] ?? null;

        if ($category !== null) {
            $query->whereIn('threads.category_id', Category::query()->select('id')->where('slug', $category))
                // Pinned threads lead their own category only.
                ->orderByDesc('is_pinned');
        }

        if (($filters['tag'] ?? null) !== null) {
            $query->whereHas('tags', fn (Builder $query) => $query->where('tags.slug', $filters['tag']));
        }

        $this->filter($query, $filters['filter'] ?? self::DEFAULT_FILTER, $viewer);

        match ($filters['sort'] ?? self::DEFAULT_SORT) {
            'newest' => $query->orderByDesc('threads.created_at'),
            'replies' => $query->orderByDesc('replies_count')->orderByDesc('last_posted_at'),
            'views' => $query->orderByDesc('views_count')->orderByDesc('last_posted_at'),
            default => $query->orderByDesc('last_posted_at'),
        };

        // A unique last key keeps pages stable when the others tie.
        return $query->orderByDesc('threads.id');
    }

    /**
     * @param  Builder<Thread>  $query
     */
    private function filter(Builder $query, string $filter, ?User $viewer): void
    {
        match ($filter) {
            'unanswered' => $query->where('replies_count', 0),
            'solved' => $query->whereNotNull('solution_post_id'),
            'unsolved' => $query->whereNull('solution_post_id'),
            default => null,
        };

        if ($viewer === null) {
            return;
        }

        match ($filter) {
            'following' => $query->whereIn('threads.id', ThreadSubscription::query()
                ->select('thread_id')
                ->where('user_id', $viewer->id)),
            'mine' => $query->where('threads.user_id', $viewer->id),
            default => null,
        };
    }
}
