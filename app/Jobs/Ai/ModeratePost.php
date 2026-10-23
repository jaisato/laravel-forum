<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Actions\Ai\ApplyModerationVerdict;
use App\Ai\Agents\ContentModerator;
use App\Ai\Data\ModerationResult;
use App\Ai\ForumAi;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Asks the AI moderator about a new or edited post, after it is published
 * (publishing never waits for the AI). When the provider fails the post
 * stays as it is and the job tries again; an answer the forum cannot vouch
 * for is logged and ignored.
 */
final class ModeratePost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Post $post) {}

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

        $applyVerdict->handle($post, $result);
    }

    private function prompt(string $title, string $body): string
    {
        return "Moderate this post.\n\n".$this->delimit('thread_title', $title)."\n\n".$this->delimit('post', $body);
    }

    /**
     * The content between its tags, which it may not close or open itself.
     */
    private function delimit(string $tag, string $content): string
    {
        $content = str_ireplace(["<{$tag}>", "</{$tag}>"], ["&lt;{$tag}&gt;", "&lt;/{$tag}&gt;"], $content);

        return "<{$tag}>\n{$content}\n</{$tag}>";
    }
}
