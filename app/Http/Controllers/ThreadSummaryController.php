<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\ForumAi;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Jobs\Ai\GenerateThreadSummary;
use App\Models\Thread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * A member asks for the thread's AI summary ("Summarize the thread"). It is
 * written in the queue, unless the stored one is still fresh; where no
 * summary applies (the feature off, a hidden thread, too few replies) the
 * request is not found.
 */
final class ThreadSummaryController extends Controller
{
    use FlashesToasts;

    public function store(Thread $thread, ForumAi $ai): RedirectResponse
    {
        abort_unless($ai->enabled('summaries') && $thread->canHaveSummary(), 404);

        Gate::authorize('summarize', $thread);

        if (! $thread->summaryIsStale()) {
            $this->toast('The summary is up to date.');

            return back();
        }

        GenerateThreadSummary::dispatch($thread);

        $this->toast('The summary is being written: it will show up here in a moment.');

        return back();
    }
}
