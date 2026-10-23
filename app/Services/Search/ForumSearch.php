<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Post;
use App\Models\Thread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as SearchBuilder;

/**
 * Full text search of threads and posts, filtered by category and tag.
 *
 * Meilisearch filters on its indexed attributes, so pages and totals are
 * exact. The collection driver (local without Meilisearch, tests) searches
 * the database, where the filters are SQL. Either way, results are loaded
 * with visibleTo(null): an index that lags behind never shows anything that
 * is no longer public.
 */
final class ForumSearch
{
    /**
     * @param  array{category?: int|null, tag?: int|null}  $filters
     * @return LengthAwarePaginator<int, Thread>
     */
    public function threads(string $query, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $categoryId = $filters['category'] ?? null;
        $tagId = $filters['tag'] ?? null;

        $search = $this->filtersInDatabase()
            ? Thread::search($query, function (Builder $threads) use ($categoryId, $tagId): Builder {
                /** @var Builder<Thread> $threads */
                return $this->constrainThreads($threads, $categoryId, $tagId);
            })
            : $this->withEngineFilters(Thread::search($query), $categoryId, $tagId);

        return $search
            ->query(function (Builder $threads): Builder {
                /** @var Builder<Thread> $threads */
                return $threads->visibleTo(null)->with(['category', 'author', 'tags']);
            })
            ->paginate($perPage);
    }

    /**
     * @param  array{category?: int|null, tag?: int|null}  $filters
     * @return LengthAwarePaginator<int, Post>
     */
    public function posts(string $query, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $categoryId = $filters['category'] ?? null;
        $tagId = $filters['tag'] ?? null;

        $search = $this->filtersInDatabase()
            ? Post::search($query, fn (Builder $posts) => $posts->whereHas(
                'thread',
                function (Builder $threads) use ($categoryId, $tagId): Builder {
                    /** @var Builder<Thread> $threads */
                    return $this->constrainThreads($threads, $categoryId, $tagId);
                },
            ))
            : $this->withEngineFilters(Post::search($query), $categoryId, $tagId);

        return $search
            ->query(function (Builder $posts): Builder {
                /** @var Builder<Post> $posts */
                return $posts
                    ->visibleTo(null)
                    ->whereHas('thread', function (Builder $threads): Builder {
                        /** @var Builder<Thread> $threads */
                        return $threads->visibleTo(null);
                    })
                    ->with(['thread', 'author']);
            })
            ->paginate($perPage);
    }

    private function filtersInDatabase(): bool
    {
        return config('scout.driver') === 'collection';
    }

    /**
     * @param  Builder<Thread>  $threads
     * @return Builder<Thread>
     */
    private function constrainThreads(Builder $threads, ?int $categoryId, ?int $tagId): Builder
    {
        return $threads
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($tagId !== null, fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tagId)));
    }

    /**
     * @template TModel of Thread|Post
     *
     * @param  SearchBuilder<TModel>  $search
     * @return SearchBuilder<TModel>
     */
    private function withEngineFilters(SearchBuilder $search, ?int $categoryId, ?int $tagId): SearchBuilder
    {
        return $search
            ->when($categoryId !== null, fn (SearchBuilder $query) => $query->where('category_id', $categoryId))
            ->when($tagId !== null, fn (SearchBuilder $query) => $query->whereIn('tag_ids', [$tagId]));
    }
}
