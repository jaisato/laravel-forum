<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Enums\ThreadStatus;
use App\Events\PostModerated;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use App\Services\Reputation;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a held or hidden post again, with its thread when it is the
 * opening post. An answer the AI held while it solved the thread solves it
 * again, bonus included, unless the thread author chose another one
 * meanwhile (HidePost, MarkSolution).
 */
final class ApprovePost
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly Reputation $reputation,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Post $post, User $moderator): void
    {
        DB::transaction(function () use ($post, $moderator): void {
            $thread = Thread::withTrashed()->whereKey($post->thread_id)->lockForUpdate()->firstOrFail();
            $post = Post::withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($post->status === PostStatus::Published) {
                return;
            }

            $post->forceFill(['status' => PostStatus::Published])->save();

            if ((int) $thread->first_post_id === $post->id) {
                $thread->forceFill(['status' => ThreadStatus::Published])->save();

                if ($this->counters->countsThread($thread)) {
                    $this->counters->applyThread($thread, 1);
                }
            } elseif ($this->counters->isVisibleReply($post, $thread)) {
                $this->counters->applyReply($post, $thread, 1);
            }

            // Never once deleted (DeletePost forgets it, and the request may
            // have loaded the answer before its deletion).
            if ((int) $thread->held_solution_post_id === $post->id) {
                $solvesAgain = $thread->solution_post_id === null && ! $post->trashed();

                $thread->forceFill([
                    'held_solution_post_id' => null,
                    ...($solvesAgain ? ['solution_post_id' => $post->id] : []),
                ])->save();

                if ($solvesAgain) {
                    $this->reputation->solution($thread, $post, 1);
                }
            }

            $this->counters->refreshLastPost($thread);

            $this->log->handle($moderator, ModerationActionType::PostApproved, $post);

            PostModerated::dispatch($post, $moderator, PostStatus::Published);
        });
    }
}
