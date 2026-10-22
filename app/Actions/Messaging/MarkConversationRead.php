<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Marks every message of the conversation as read for the member.
 */
final class MarkConversationRead
{
    public function handle(Conversation $conversation, User $user): void
    {
        $lastMessageId = Conversation::query()->whereKey($conversation->id)->value('last_message_id');

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => (int) $lastMessageId,
                'last_read_at' => now(),
            ]);
    }
}
