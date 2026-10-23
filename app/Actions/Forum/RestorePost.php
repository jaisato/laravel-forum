<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use Illuminate\Support\Facades\DB;

/**
 * Brings back a deleted reply with its contribution to the counters.
 * Restoring a post that is not deleted changes nothing.
 */
final class RestorePost
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Post $post, User $actor): void
    {
        DB::transaction(function () use ($post, $actor): void {
            $thread = Thread::withTrashed()->whereKey($post->thread_id)->lockForUpdate()->firstOrFail();
            $post = Post::onlyTrashed()->whereKey($post->id)->lockForUpdate()->first();

            if ($post === null) {
                return;
            }

            $post->restore();

            if ($this->counters->isVisibleReply($post, $thread)) {
                $this->counters->applyReply($post, $thread, 1);
            }

            $this->counters->refreshLastPost($thread);

            if (! $post->isAuthoredBy($actor)) {
                $this->log->handle($actor, ModerationActionType::PostRestored, $post);
            }
        });
    }
}
