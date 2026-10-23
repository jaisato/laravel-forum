<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation in the signed in member's inbox: who else is in it, the
 * last message and whether it has messages they have not read. Expects it
 * loaded through the member's conversations() relation (their membership
 * is the pivot), with participants (the active ones) and lastMessage.author.
 *
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    private const int EXCERPT_LENGTH = 140;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->resource;
        /** @var User $member */
        $member = $request->user();
        /** @var ConversationParticipant $membership */
        $membership = $conversation->getRelation('pivot');
        $last = $conversation->lastMessage;

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'is_unread' => (int) $conversation->last_message_id > $membership->last_read_message_id,
            'participants' => $conversation->participants
                ->reject(fn (User $participant): bool => $participant->is($member))
                ->map(fn (User $participant): array => (new UserResource($participant))->resolve($request))
                ->values()
                ->all(),
            'last_message' => $last === null ? null : [
                'excerpt' => app(MarkdownRenderer::class)->excerpt(mb_substr($last->body, 0, 600), self::EXCERPT_LENGTH),
                'author' => UserResource::orErased($last->author)?->resolve($request),
            ],
        ];
    }
}
