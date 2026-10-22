<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Events\PostUpdated;
use App\Models\Post;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use App\Support\Markdown\MentionParser;
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

        $previousMentions = $this->mentions->extract($post->body);
        $bodyHtml = $this->markdown->render($body);

        return DB::transaction(function () use ($post, $editor, $body, $bodyHtml, $previousMentions): Post {
            $post->forceFill([
                'body' => $body,
                'body_html' => $bodyHtml,
                'edited_at' => now(),
                'edited_by_id' => $editor->id,
            ])->save();

            if (! $post->isAuthoredBy($editor)) {
                $this->log->handle($editor, ModerationActionType::PostEdited, $post);
            }

            PostUpdated::dispatch($post, $previousMentions);

            return $post;
        });
    }
}
