<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Thread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Similar threads by their words, through the full text search (Scout):
 * Meilisearch ranks them, the collection driver matches the text. Results
 * are loaded with visibleTo(null) and without the deleted ones, as in
 * ForumSearch, so an index that lags behind never suggests a thread that is
 * no longer public.
 */
final class KeywordSimilarThreadFinder implements SimilarThreadFinder
{
    public function similarTo(Thread $thread, int $limit = 5): Collection
    {
        return $this->forText($thread->title, $limit, $thread->id);
    }

    public function forText(string $text, int $limit = 5, ?int $exceptThreadId = null): Collection
    {
        $text = trim($text);

        if ($text === '' || $limit < 1) {
            return collect();
        }

        return Thread::search($text)
            ->query(function (Builder $threads): Builder {
                /** @var Builder<Thread> $threads */
                return $threads->withoutTrashed()->visibleTo(null);
            })
            // One more, in case the thread to leave out is among them.
            ->take($limit + 1)
            ->get()
            ->reject(fn (Thread $thread): bool => $thread->id === $exceptThreadId)
            ->take($limit)
            ->values();
    }
}
