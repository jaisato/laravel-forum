<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A member's private conversations as their inbox lists them, on the web
 * and in the API: those they have not left, with the other participants and
 * the last message, the latest activity first. ConversationResource reads
 * the member's own participation (the pivot) from them.
 */
final class MemberConversations
{
    /**
     * @return LengthAwarePaginator<int, Conversation&object{pivot: ConversationParticipant}>
     */
    public function paginate(User $member): LengthAwarePaginator
    {
        return $this->query($member)
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id')
            ->paginate((int) config('forum.pagination.conversations'));
    }

    /**
     * One of them, loaded as paginate() loads them.
     */
    public function one(User $member, Conversation $conversation): Conversation
    {
        return $this->query($member)->whereKey($conversation->id)->firstOrFail();
    }

    /**
     * The participants still in a conversation, in the order they joined
     * (whoever started it first).
     *
     * @param  BelongsToMany<User, Conversation, ConversationParticipant>  $participants
     */
    public static function activeParticipants(BelongsToMany $participants): void
    {
        $participants->wherePivotNull('left_at')->orderBy('conversation_participants.id');
    }

    /**
     * @return BelongsToMany<Conversation, User, ConversationParticipant>
     */
    private function query(User $member): BelongsToMany
    {
        return $member->conversations()
            ->wherePivotNull('left_at')
            ->with(['participants' => self::activeParticipants(...), 'lastMessage.author']);
    }
}
