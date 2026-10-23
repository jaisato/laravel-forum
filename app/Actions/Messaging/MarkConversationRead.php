<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Marks the messages of the conversation as read for the member: all of
 * them, or up to the last one they got to see. The marker only moves
 * forward: a message the member sent meanwhile already moved it past what
 * this request saw.
 */
final class MarkConversationRead
{
    /**
     * @param  int|null  $upTo  the last message the member got to see (a page of
     *                          them, in the API); the whole conversation when null
     */
    public function handle(Conversation $conversation, User $user, ?int $upTo = null): void
    {
        $lastMessageId = (int) Conversation::query()->whereKey($conversation->id)->value('last_message_id');

        if ($upTo !== null) {
            $lastMessageId = min($lastMessageId, $upTo);
        }

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
