<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\Post;
use App\Notifications\ThreadReplied;
use App\Support\Markdown\MentionParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the subscribers of a thread about a new reply: not its author, and
 * not the members the reply mentions (they get the mention instead).
 */
final class NotifyThreadSubscribers implements ShouldQueue
{
    public function __construct(private readonly MentionParser $mentions) {}

    public function handle(PostCreated $event): void
    {
        $post = Post::query()->with('thread')->find($event->post->id);

        if ($post === null || ! $post->isPublic()) {
            return;
        }

        $mentioned = $this->mentions->extract($post->body);

        $post->thread->subscribers()
            ->whereKeyNot($post->user_id)
            ->when($mentioned !== [], fn ($query) => $query->whereNotIn('users.username', $mentioned))
            ->chunkById(500, function (Collection $subscribers) use ($post): void {
                Notification::send($subscribers, new ThreadReplied($post));
            });
    }
}
