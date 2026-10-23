<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Ai\ForumAi;
use App\Http\Controllers\Controller;
use App\Http\Resources\ThreadSummaryResource;
use App\Jobs\Ai\GenerateThreadSummary;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * A thread's AI summary (spec §4.6). Reading it is public; asking for one
 * (members with the write ability) queues it unless the stored one is
 * fresh. Where no summary applies, or AI summaries are off, the answer is
 * 404.
 */
final class ThreadSummaryController extends Controller
{
    /**
     * The stored summary, stale or not, while the thread still qualifies
     * for one.
     *
     * @unauthenticated
     */
    public function show(Thread $thread, ForumAi $ai): ThreadSummaryResource
    {
        abort_unless($ai->enabled('summaries'), 404);

        Gate::authorize('view', $thread);

        abort_if($thread->ai_summary === null || ! $thread->canHaveSummary(), 404);

        return new ThreadSummaryResource($thread);
    }

    /**
     * Asks for the summary: 202 while it is written, 200 with it when the
     * stored one is fresh.
     */
    public function store(Thread $thread, ForumAi $ai): ThreadSummaryResource|JsonResponse
    {
        abort_unless($ai->enabled('summaries') && $thread->canHaveSummary(), 404);

        Gate::authorize('summarize', $thread);

        if (! $thread->summaryIsStale()) {
            return new ThreadSummaryResource($thread);
        }

        GenerateThreadSummary::dispatch($thread);

        return response()->json(['message' => __('The summary is being written: it will show up here in a moment.')], 202);
    }
}
