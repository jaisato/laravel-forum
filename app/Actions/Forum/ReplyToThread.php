<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use App\Services\ReadTracker;
use App\Services\ThreadSubscriptions;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Adds a reply. The thread row is locked first, which serialises the
 * replies of a thread: counters stay exact and post ids follow commit
 * order, which the read markers rely on.
 */
final class ReplyToThread
{
    public function __construct(
        private readonly MarkdownRenderer $markdown,
        private readonly ForumCounters $counters,
        private readonly ThreadSubscriptions $subscriptions,
        private readonly ReadTracker $reads,
    ) {}

    public function handle(Thread $thread, User $author, string $body, ?Post $replyTo = null): Post
    {
        if ($replyTo !== null && (int) $replyTo->thread_id !== $thread->id) {
            throw new InvalidArgumentException('A reply can only quote a post of its own thread.');
        }

        $bodyHtml = $this->markdown->render($body);

        return DB::transaction(function () use ($thread, $author, $body, $bodyHtml, $replyTo): Post {
            $thread = Thread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();

            // The request was authorised on the copy it loaded: a moderator
            // may have locked or hidden the thread since.
            if (! $thread->acceptsRepliesFrom($author)) {
                throw new AuthorizationException(__('This thread no longer accepts replies.'));
            }

            $post = $thread->posts()->create([
                'user_id' => $author->id,
                'reply_to_id' => $replyTo?->id,
                'body' => $body,
                'body_html' => $bodyHtml,
            ]);
            $post->setRelation('thread', $thread);

            $thread->forceFill([
                'last_post_id' => $post->id,
                'last_posted_at' => $post->created_at,
            ])->save();

            if ($this->counters->isVisibleReply($post, $thread)) {
                $this->counters->applyReply($post, $thread, 1);
            }

            $this->subscriptions->subscribe($thread, $author);
            $this->reads->markRead($thread, $author);

            PostCreated::dispatch($post);

            return $post;
        });
    }
}
