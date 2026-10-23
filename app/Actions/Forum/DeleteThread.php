<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Events\ThreadDeleted;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soft deletes a thread with everything in it (staff can restore it).
 * Deleting twice changes nothing.
 */
final class DeleteThread
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Thread $thread, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($thread, $actor, $reason): void {
            $thread = Thread::query()->whereKey($thread->id)->lockForUpdate()->first();

            if ($thread === null) {
                return;
            }

            // The request was authorised on the copy it loaded: somebody may
            // have replied since, and authors may only delete unanswered threads.
            if (! $thread->mayBeDeletedBy($actor->current())) {
                throw new AuthorizationException(__('This thread can no longer be deleted.'));
            }

            if ($this->counters->countsThread($thread)) {
                $this->counters->applyThread($thread, -1);
            }

            $thread->delete();

            ThreadDeleted::dispatch($thread);

            if (! $thread->isAuthoredBy($actor)) {
                $this->log->handle($actor, ModerationActionType::ThreadDeleted, $thread, $reason);
            }
        });
    }
}
