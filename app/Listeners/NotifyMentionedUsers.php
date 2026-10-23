<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostCreated;
use App\Events\PostUpdated;
use App\Models\Post;
use App\Models\User;
use App\Notifications\UserMentioned;
use App\Support\Markdown\MentionParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells members they were mentioned. After an edit only the members newly
 * mentioned are told, so fixing a typo never notifies anyone twice.
 */
final class NotifyMentionedUsers implements ShouldQueue
{
    public function __construct(private readonly MentionParser $mentions) {}

    public function handlePostCreated(PostCreated $event): void
    {
        $this->notify($event->post->id, $event->mentions, []);
    }

    public function handlePostUpdated(PostUpdated $event): void
    {
        $this->notify($event->post->id, $event->mentions, $event->previousMentions);
    }

    /**
     * @param  list<string>  $mentioned  usernames the revision mentioned
     * @param  list<string>  $before  usernames the revision before it mentioned
     */
    private function notify(int $postId, array $mentioned, array $before): void
    {
        $post = Post::query()->with('thread')->find($postId);

        if ($post === null || ! $post->isPublic()) {
            return;
        }

        $usernames = $this->mentions->stillNew($mentioned, $before, $post->body);

        if ($usernames === []) {
            return;
        }

        $mentioned = User::query()
            ->whereIn('username', $usernames)
            ->whereKeyNot($post->user_id)
            ->get();

        Notification::send($mentioned, new UserMentioned($post));
    }
}
