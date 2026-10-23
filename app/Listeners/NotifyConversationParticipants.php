<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Notifications\NewPrivateMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the other active participants about a new private message.
 */
final class NotifyConversationParticipants implements ShouldQueue
{
    public function handle(MessageSent $event): void
    {
        $conversation = Conversation::query()->find($event->message->conversation_id);

        if ($conversation === null) {
            return;
        }

        $recipients = $conversation->participants()
            ->wherePivotNull('left_at')
            ->whereKeyNot($event->message->user_id)
            ->get();

        Notification::send($recipients, new NewPrivateMessage($event->message));
    }
}
