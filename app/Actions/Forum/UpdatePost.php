<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Events\PostUpdated;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use App\Support\Markdown\MentionParser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Edits a post and marks it as edited. An unchanged body is not an edit.
 * Edits by staff on posts of others are logged.
 */
final class UpdatePost
{
    public function __construct(
        private readonly MarkdownRenderer $markdown,
        private readonly MentionParser $mentions,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Post $post, User $editor, string $body): Post
    {
        if ($body === $post->body) {
            return $post;
        }

        $bodyHtml = $this->markdown->render($body);
        $mentioned = $this->mentions->extract($body);

        return DB::transaction(function () use ($post, $editor, $body, $bodyHtml, $mentioned): Post {
            // Thread first, then post: the lock order of every action.
            $thread = Thread::withTrashed()->whereKey($post->thread_id)->lockForUpdate()->firstOrFail();
            $stored = Post::withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $stored->setRelation('thread', $thread);

            // The request was authorised on the copy it loaded: a moderator
            // may have hidden the post or locked the thread since.
            if (! $stored->mayBeChangedBy($editor->current())) {
                throw new AuthorizationException(__('This post can no longer be changed.'));
            }

            if ($body !== $stored->body) {
                $previousMentions = $this->mentions->extract($stored->body);

                $stored->forceFill([
                    'body' => $body,
                    'body_html' => $bodyHtml,
                    'edited_at' => now(),
                    'edited_by_id' => $editor->id,
                ])->save();

                if (! $stored->isAuthoredBy($editor)) {
                    $this->log->handle($editor, ModerationActionType::PostEdited, $stored);
                }

                PostUpdated::dispatch($stored, $previousMentions, $mentioned);
            }

            $post->setRawAttributes($stored->getAttributes(), sync: true);

            return $stored;
        });
    }
}
