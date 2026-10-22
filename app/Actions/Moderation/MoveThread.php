<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use Illuminate\Support\Facades\DB;

/**
 * Moves a thread to another category, with its share of the counters.
 */
final class MoveThread
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Thread $thread, Category $category, User $moderator): void
    {
        DB::transaction(function () use ($thread, $category, $moderator): void {
            $thread = Thread::withTrashed()->whereKey($thread->id)->lockForUpdate()->firstOrFail();
            $from = $thread->category_id;

            if ($from === $category->id) {
                return;
            }

            if ($this->counters->countsThread($thread)) {
                $this->counters->moveThread($thread, $from, $category->id);
            }

            $thread->forceFill(['category_id' => $category->id])->save();

            $this->log->handle($moderator, ModerationActionType::ThreadMoved, $thread, meta: [
                'from_category_id' => $from,
                'to_category_id' => $category->id,
            ]);
        });
    }
}
