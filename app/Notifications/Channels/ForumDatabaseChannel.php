<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\ForumNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Stores who caused each forum notification in its own column, so erasing
 * an account finds the notifications it caused for others (DeleteAccount)
 * with an indexed query instead of searching the JSON payloads.
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
        ];
    }
}
