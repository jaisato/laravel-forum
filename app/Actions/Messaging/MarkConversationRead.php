<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Marks every message of the conversation as read for the member. The
 * marker only moves forward: a message the member sent meanwhile already
 * moved it past what this request saw.
 */
final class MarkConversationRead
{
    public function handle(Conversation $conversation, User $user): void
    {
        $lastMessageId = (int) Conversation::query()->whereKey($conversation->id)->value('last_message_id');

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('last_read_message_id', '<=', $lastMessageId)
            ->update([
                'last_read_message_id' => $lastMessageId,
                'last_read_at' => now(),
            ]);
    }
}
