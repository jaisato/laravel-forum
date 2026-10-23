<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Adds a message to a conversation. Locking the conversation serialises its
 * messages, so their ids follow commit order (the read watermarks rely on
 * that). The sender's own message never counts as unread.
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

            if ($membership === null) {
                throw new InvalidArgumentException('Only active participants can write to a conversation.');
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
