<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Adds a message to a conversation. Locking the conversation serialises its
 * messages, so their ids follow commit order (the read watermarks rely on
 * that). The sender's own message never counts as unread. Under the lock it
 * checks again that the sender, as stored now, still takes part in the
 * conversation and may write (ConversationPolicy::reply): they may have
 * left it or been banned while their request ran.
 */
final class SendMessage
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    public function handle(Conversation $conversation, User $sender, string $body): Message
    {
        $bodyHtml = $this->markdown->render($body);

        return DB::transaction(function () use ($conversation, $sender, $body, $bodyHtml): Message {
            $conversation = Conversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $membership = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->whereNull('left_at')
                ->first();

            if ($membership === null || ! $sender->current()->canParticipate()) {
                throw new AuthorizationException(__('You can no longer write to this conversation.'));
            }

            $message = $conversation->messages()->create([
                'user_id' => $sender->id,
                'body' => $body,
                'body_html' => $bodyHtml,
            ]);

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ])->save();

            $membership->forceFill([
                'last_read_message_id' => $message->id,
                'last_read_at' => now(),
            ])->save();

            MessageSent::dispatch($message);

            return $message;
        });
    }
}
