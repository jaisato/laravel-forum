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
        if ($thread->is_pinned === $pinned) {
            return;
        }

        DB::transaction(function () use ($thread, $moderator, $pinned): void {
            $thread->forceFill(['is_pinned' => $pinned])->save();

            $this->log->handle($moderator, $pinned ? ModerationActionType::ThreadPinned : ModerationActionType::ThreadUnpinned, $thread);
        });
    }
}
