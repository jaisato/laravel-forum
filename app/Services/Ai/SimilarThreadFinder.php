<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Thread;
use Illuminate\Support\Collection;

/**
 * Public threads that look like a text: the hint under the title of a new
 * thread, so members find an existing answer before they ask again.
 */
interface SimilarThreadFinder
{
    /**
     * The most similar first.
     *
     * @return Collection<int, Thread>
     */
    public function forText(string $text, int $limit = 5, ?int $exceptThreadId = null): Collection;
}
