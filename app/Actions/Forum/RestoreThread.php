<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use Illuminate\Support\Facades\DB;

/**
 * Brings back a deleted thread with its contribution to the counters.
 * Restoring a thread that is not deleted changes nothing.
 */
final class RestoreThread
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Thread $thread, User $actor): void
    {
        DB::transaction(function () use ($thread, $actor): void {
            $thread = Thread::onlyTrashed()->whereKey($thread->id)->lockForUpdate()->first();

            if ($thread === null) {
                return;
            }

            $thread->restore();

            if ($this->counters->countsThread($thread)) {
                $this->counters->applyThread($thread, 1);
            }

            if (! $thread->isAuthoredBy($actor)) {
                $this->log->handle($actor, ModerationActionType::ThreadRestored, $thread);
            }
        });
    }
}
