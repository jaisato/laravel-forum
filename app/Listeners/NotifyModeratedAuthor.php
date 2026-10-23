<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostModerated;
use App\Models\User;
use App\Notifications\PostModerated as PostModeratedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells authors when their post is held, hidden or approved, unless they
 * moderated their own post.
 */
final class NotifyModeratedAuthor implements ShouldQueue
{
    public function handle(PostModerated $event): void
    {
        if ($event->moderator !== null && $event->moderator->id === (int) $event->post->user_id) {
            return;
        }

        User::query()->find($event->post->user_id)?->notify(
            new PostModeratedNotification($event->post, $event->status, $event->reason),
        );
    }
}
