<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Thread;
use Illuminate\Support\Collection;

/**
 * Public threads that look like another or like a text: the related threads
 * of a thread page, and the hint under the title of a new thread, so members
 * find an existing answer before they ask again. Embeddings when
 * ForumAi::usesVectorSearch(), keyword search otherwise.
 */
interface SimilarThreadFinder
{
    /**
     * The public threads most like this one, never itself.
     *
     * @return Collection<int, Thread>
     */
    public function similarTo(Thread $thread, int $limit = 5): Collection;

    /**
     * The most similar first.
     *
     * @return Collection<int, Thread>
     */
    public function forText(string $text, int $limit = 5, ?int $exceptThreadId = null): Collection;
}
