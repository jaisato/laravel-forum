<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Locks a thread (read-only for members) or opens it again.
 */
final class LockThread
{
    public function __construct(private readonly RecordModerationAction $log) {}

    public function handle(Thread $thread, User $moderator, bool $locked = true): void
    {
        DB::transaction(function () use ($thread, $moderator, $locked): void {
            // Decide on the stored row, locked: the copy the request loaded
            // may be stale (a double click, another moderator).
            $stored = Thread::withTrashed()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            if ($stored->is_locked !== $locked) {
                $stored->forceFill(['is_locked' => $locked])->save();

                $this->log->handle($moderator, $locked ? ModerationActionType::ThreadLocked : ModerationActionType::ThreadUnlocked, $stored);
            }

            $thread->setRawAttributes($stored->getAttributes(), sync: true);
        });
    }
}
