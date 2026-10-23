<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\ForumNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Stores who caused each forum notification and the post it is about in
 * their own columns: erasing an account finds the notifications it caused
 * for others (DeleteAccount) with an indexed query instead of searching the
 * JSON payloads, and a notification about a post that is no longer public
 * stops showing it (UserNotification::isWithheldFrom()).
 */
final class ForumDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'actor_id' => $notification instanceof ForumNotification ? $notification->actorId() : null,
            'post_id' => $notification instanceof ForumNotification ? $notification->postId() : null,
        ];
    }
}
