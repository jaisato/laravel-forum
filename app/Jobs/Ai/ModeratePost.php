<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Actions\Ai\ApplyModerationVerdict;
use App\Ai\Agents\ContentModerator;
use App\Ai\Data\ModerationResult;
use App\Ai\ForumAi;
use App\Ai\UntrustedText;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Asks the AI moderator about a new or edited post, after it is published
 * (publishing never waits for the AI). When the provider fails the post
 * stays as it is and the job tries again; an answer the forum cannot vouch
 * for is logged and ignored.
 *
 * One job per post waits at a time: it reads the post as it stands when it
 * runs, so the edits made meanwhile cost no further call. The lock lapses
 * after ten minutes in case a job is lost on the way.
 */
#[Queue('ai')]
#[UniqueFor(600)]
final class ModeratePost implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Post $post) {}

    public function uniqueId(): string
    {
        return (string) $this->post->id;
    }

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ForumAi $ai, ApplyModerationVerdict $applyVerdict): void
    {
        if (! $ai->enabled('moderation')) {
            return;
        }

        // As it stands now: it may have been hidden or deleted since.
        $post = Post::query()->find($this->post->id);
        $thread = $post === null ? null : Thread::withTrashed()->find($post->thread_id);

        if ($post === null || $thread === null || $post->status !== PostStatus::Published) {
            return;
        }

        $output = ContentModerator::make()->prompt($this->prompt($thread->title, $post->body))->toArray();
        $result = ModerationResult::fromArray($output);

        if ($result === null) {
            Log::warning('The AI moderator gave an answer outside its schema; the post was left as it is.', [
                'post_id' => $post->id,
                'output' => $output,
            ]);

            return;
        }

        // The provider takes a while, and the post or its thread's title may
        // have been edited meanwhile: the verdict is about the text it read.
        // It stands only if the post still reads the same, checked under the
        // thread's lock that edits and moderation take too. Otherwise the job
        // asks again about the post as it is now (a no-op when the edit already
        // queued that question).
        $stands = DB::transaction(function () use ($post, $thread, $result, $applyVerdict): bool {
            $title = Thread::withTrashed()->whereKey($thread->id)->lockForUpdate()->value('title');
            $body = Post::query()->whereKey($post->id)->value('body');

            if ($title !== $thread->title || $body !== $post->body) {
                return false;
            }

            $applyVerdict->handle($post, $result);

            return true;
        });

        if (! $stands) {
            self::dispatch($post);
        }
    }

    private function prompt(string $title, string $body): string
    {
        return "Moderate this post.\n\n".UntrustedText::wrap('thread_title', $title)."\n\n".UntrustedText::wrap('post', $body);
    }
}
