<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Private conversations are visible to their active participants only,
 * staff included: there is no moderator override.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isActiveParticipant($user, $conversation);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $user->canParticipate() && $this->isActiveParticipant($user, $conversation);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $this->isActiveParticipant($user, $conversation);
    }

    /**
     * Starting a conversation: check with can('message', [Conversation::class,
     * $recipient]). Staff may write to members who turned private messages
     * off, so moderation can always reach them.
     */
    public function message(User $sender, User $recipient): bool
    {
        return $sender->canParticipate()
            && $sender->isNot($recipient)
            && ! $recipient->trashed()
            && ($recipient->acceptsPrivateMessages() || $sender->canModerate());
    }

    private function isActiveParticipant(User $user, Conversation $conversation): bool
    {
        if ($conversation->relationLoaded('participants')) {
            return $conversation->participants->contains(function (User $participant) use ($user): bool {
                /** @var ConversationParticipant $membership */
                $membership = $participant->getRelation('pivot');

                return $participant->is($user) && $membership->left_at === null;
            });
        }

        return $conversation->participants()
            ->whereKey($user->id)
            ->wherePivotNull('left_at')
            ->exists();
    }
}
