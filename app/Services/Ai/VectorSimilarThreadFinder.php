<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\ForumAi;
use App\Models\Thread;
use App\Models\ThreadEmbedding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Similar threads by meaning (pgvector): the stored embeddings of threads
 * (GenerateThreadEmbedding) nearest to the thread's own, or to the text's,
 * above forum.ai.similar.min_similarity, public threads only. A thread
 * without an embedding yet, or a provider that fails, falls back to
 * keyword search.
 */
final class VectorSimilarThreadFinder implements SimilarThreadFinder
{
    public function __construct(
        private readonly ForumAi $ai,
        private readonly KeywordSimilarThreadFinder $keywords,
    ) {}

    public function similarTo(Thread $thread, int $limit = 5): Collection
    {
        $embedding = ThreadEmbedding::query()->find($thread->id);

        if ($embedding === null) {
            return $this->keywords->similarTo($thread, $limit);
        }

        /** @var array<int, float> $vector */
        $vector = $embedding->embedding;

        return $this->nearest($vector, $limit, $thread->id);
    }

    public function forText(string $text, int $limit = 5, ?int $exceptThreadId = null): Collection
    {
        $text = trim($text);

        if ($text === '' || $limit < 1) {
            return collect();
        }

        try {
            $vector = Embeddings::for([$text])
                ->dimensions($this->ai->embeddingsDimensions())
                ->cache()
                ->generate($this->ai->embeddingsProvider(), $this->ai->embeddingsModel())
                ->first();
        } catch (Throwable $exception) {
            Log::warning('Embeddings are unavailable: similar threads come from keyword search.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->keywords->forText($text, $limit, $exceptThreadId);
        }

        return $this->nearest($vector, $limit, $exceptThreadId);
    }

    /**
     * @param  array<int, float>  $vector
     * @return Collection<int, Thread>
     */
    private function nearest(array $vector, int $limit, ?int $exceptThreadId): Collection
    {
        return Thread::query()
            ->visibleTo(null)
            ->select('threads.*')
            ->join('thread_embeddings', 'thread_embeddings.thread_id', '=', 'threads.id')
            ->whereVectorSimilarTo('thread_embeddings.embedding', $vector, (float) config('forum.ai.similar.min_similarity'))
            ->when($exceptThreadId !== null, fn ($threads) => $threads->where('threads.id', '<>', $exceptThreadId))
            ->limit($limit)
            ->get();
    }
}
