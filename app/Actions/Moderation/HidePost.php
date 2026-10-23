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
use Illuminate\Support\Facades\DB;

/**
 * Takes a post out of public sight. A moderator hides it for good (staff
 * only); the system ($moderator null, the AI moderator) only holds it for
 * review, which still shows it to its author. The opening post takes its
 * thread along, since the thread is its first post.
 */
final class HidePost
{
    public function __construct(
        private readonly ForumCounters $counters,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Post $post, ?User $moderator, ?string $reason = null): void
    {
        $status = $moderator === null ? PostStatus::PendingReview : PostStatus::Hidden;

        DB::transaction(function () use ($post, $moderator, $reason, $status): void {
            $thread = Thread::withTrashed()->whereKey($post->thread_id)->lockForUpdate()->firstOrFail();
            $post = Post::withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            // Nothing to do, and the system never softens a moderator's decision.
            if ($post->status === $status || $post->status === PostStatus::Hidden) {
                return;
            }

            if ((int) $thread->first_post_id === $post->id) {
                if ($this->counters->countsThread($thread)) {
                    $this->counters->applyThread($thread, -1);
                }

                $thread->forceFill([
                    'status' => $status === PostStatus::Hidden ? ThreadStatus::Hidden : ThreadStatus::PendingReview,
                ])->save();
            } elseif ($this->counters->isVisibleReply($post, $thread)) {
                $this->counters->applyReply($post, $thread, -1);
            }

            $post->forceFill(['status' => $status])->save();

            if ((int) $thread->last_post_id === $post->id) {
                $this->counters->refreshLastPost($thread);
            }

            $this->log->handle($moderator, ModerationActionType::PostHidden, $post, $reason, ['status' => $status->value]);

            PostModerated::dispatch($post, $moderator, $status, $reason);
        });
    }
}
