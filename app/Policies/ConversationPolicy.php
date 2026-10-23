<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Private conversations are visible to their active participants only,
 * staff included: there is no moderator override. Everyone else gets a 404,
 * so a conversation does not even show it exists.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): Response
    {
        return $this->isActiveParticipant($user, $conversation) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Banned or unverified participants still read the conversation, but
     * may not write to it.
     */
    public function reply(User $user, Conversation $conversation): Response
    {
        if (! $this->isActiveParticipant($user, $conversation)) {
            return Response::denyAsNotFound();
        }

        return $user->canParticipate() ? Response::allow() : Response::deny();
    }

    public function leave(User $user, Conversation $conversation): Response
    {
        return $this->view($user, $conversation);
    }

    /**
     * Opening the new conversation form; who may be written to is
     * message().
     */
    public function create(User $user): bool
    {
        return $user->canParticipate();
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
