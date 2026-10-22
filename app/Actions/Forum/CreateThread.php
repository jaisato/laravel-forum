<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Events\PostCreated;
use App\Events\ThreadCreated;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Services\ForumCounters;
use App\Services\ReadTracker;
use App\Services\ThreadSubscriptions;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Support\Facades\DB;

/**
 * Opens a thread: the thread, its opening post (the body), its tags, the
 * counters, and the author's subscription and read marker, in one
 * transaction.
 */
final class CreateThread
{
    public function __construct(
        private readonly MarkdownRenderer $markdown,
        private readonly ForumCounters $counters,
        private readonly ThreadSubscriptions $subscriptions,
        private readonly ReadTracker $reads,
    ) {}

    /**
     * @param  array<int, int|string>  $tagIds
     */
    public function handle(User $author, Category $category, string $title, string $body, array $tagIds = []): Thread
    {
        $bodyHtml = $this->markdown->render($body);

        return DB::transaction(function () use ($author, $category, $title, $body, $bodyHtml, $tagIds): Thread {
            $thread = new Thread(['title' => $title]);
            $thread->category()->associate($category);
            $thread->author()->associate($author);
            $thread->save();

            $post = $thread->posts()->create([
                'user_id' => $author->id,
                'body' => $body,
                'body_html' => $bodyHtml,
            ]);
            $post->setRelation('thread', $thread);

            $thread->forceFill([
                'first_post_id' => $post->id,
                'last_post_id' => $post->id,
                'last_posted_at' => $post->created_at,
            ])->save();

            $thread->tags()->sync(Tag::existingIds($tagIds));

            $this->counters->applyThread($thread, 1);
            $this->subscriptions->subscribe($thread, $author);
            $this->reads->markRead($thread, $author);

            ThreadCreated::dispatch($thread);
            PostCreated::dispatch($post);

            return $thread;
        });
    }
}
