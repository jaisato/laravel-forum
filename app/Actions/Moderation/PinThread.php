<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pins a thread to the top of its category, or unpins it.
 */
final class PinThread
{
    public function __construct(private readonly RecordModerationAction $log) {}

    public function handle(Thread $thread, User $moderator, bool $pinned = true): void
    {
        DB::transaction(function () use ($thread, $moderator, $pinned): void {
            // Decide on the stored row, locked: the copy the request loaded
            // may be stale (a double click, another moderator).
            $stored = Thread::withTrashed()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            if ($stored->is_pinned !== $pinned) {
                $stored->forceFill(['is_pinned' => $pinned])->save();

                $this->log->handle($moderator, $pinned ? ModerationActionType::ThreadPinned : ModerationActionType::ThreadUnpinned, $stored);
            }

            $thread->setRawAttributes($stored->getAttributes(), sync: true);
        });
    }
}
