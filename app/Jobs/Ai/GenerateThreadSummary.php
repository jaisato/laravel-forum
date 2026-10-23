<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Actions\Ai\SummarizeThread;
use App\Ai\ForumAi;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes a thread's summary in the queue, once at a time per thread, and
 * only if it is still wanted when its turn comes: the feature on, the
 * thread public with enough replies and its summary stale.
 */
final class GenerateThreadSummary implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds a summary request blocks another for the same thread. */
    public int $uniqueFor = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Thread $thread) {}

    public function uniqueId(): string
    {
        return (string) $this->thread->id;
    }

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ForumAi $ai, SummarizeThread $summarizeThread): void
    {
        if (! $ai->enabled('summaries')) {
            return;
        }

        $thread = Thread::query()->find($this->thread->id);

        if ($thread === null || ! $thread->canHaveSummary() || ! $thread->summaryIsStale()) {
            return;
        }

        $summarizeThread->handle($thread);
    }
}
