<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Events\PostDeleted;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use App\Services\Reputation;
use Illuminate\Support\Facades\DB;

/**
 * Soft deletes a reply (staff can restore it). Deleting the opening post
 * deletes the thread. Deleting twice changes nothing.
 */
final class DeletePost
{
    public function __construct(
        private readonly DeleteThread $deleteThread,
        private readonly ForumCounters $counters,
        private readonly Reputation $reputation,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Post $post, User $actor, ?string $reason = null): void
    {
        if ($post->isFirst()) {
            $thread = Thread::query()->find($post->thread_id);

            if ($thread !== null) {
                $this->deleteThread->handle($thread, $actor, $reason);
            }

            return;
        }

        DB::transaction(function () use ($post, $actor, $reason): void {
            // Thread first, then post: the same lock order as every action.
            $thread = Thread::withTrashed()->whereKey($post->thread_id)->lockForUpdate()->firstOrFail();
            $post = Post::query()->whereKey($post->id)->lockForUpdate()->first();

            if ($post === null) {
                return;
            }

            $counted = $this->counters->countsReply($post, $thread);

            $post->delete();

            if ($counted) {
                $this->counters->applyReply($post, $thread, -1);
            }

            // A deleted answer no longer solves the thread, and its author
            // loses the bonus the solution earned.
            if ((int) $thread->solution_post_id === $post->id) {
                $thread->forceFill(['solution_post_id' => null])->save();
                $this->reputation->solution($thread, $post, -1);
            }

            if ((int) $thread->last_post_id === $post->id) {
                $this->counters->refreshLastPost($thread);
            }

            if (! $post->isAuthoredBy($actor)) {
                $this->log->handle($actor, ModerationActionType::PostDeleted, $post, $reason);
            }

            PostDeleted::dispatch($post);
        });
    }
}
