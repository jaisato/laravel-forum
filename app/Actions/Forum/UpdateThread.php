<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Events\ThreadUpdated;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use Illuminate\Support\Facades\DB;

/**
 * Changes the title and tags of a thread. The slug never changes, so links
 * keep working; edits by staff on threads of others are logged.
 */
final class UpdateThread
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly RecordModerationAction $log,
    ) {}

    /**
     * @param  array<int, int|string>  $tagIds
     */
    public function handle(Thread $thread, User $editor, string $title, array $tagIds): Thread
    {
        return DB::transaction(function () use ($thread, $editor, $title, $tagIds): Thread {
            $previousTitle = $thread->title;

            $thread->fill(['title' => $title])->save();

            /** @var array{attached: array<int, int>, detached: array<int, int>} $tags */
            $tags = $thread->tags()->sync(Tag::existingIds($tagIds));

            if ($this->counters->countsThread($thread)) {
                $this->counters->applyTags($tags['attached'], 1);
                $this->counters->applyTags($tags['detached'], -1);
            }

            if (! $thread->wasChanged('title') && $tags['attached'] === [] && $tags['detached'] === []) {
                return $thread;
            }

            if (! $thread->isAuthoredBy($editor)) {
                $this->log->handle($editor, ModerationActionType::ThreadEdited, $thread, meta: [
                    'previous_title' => $previousTitle,
                    'title' => $thread->title,
                ]);
            }

            ThreadUpdated::dispatch($thread);

            return $thread;
        });
    }
}
