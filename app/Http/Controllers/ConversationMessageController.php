<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messaging\SendMessage;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Replies in a private conversation.
 */
final class ConversationMessageController extends Controller
{
    use FlashesToasts;

    public function store(StoreMessageRequest $request, Conversation $conversation, SendMessage $sendMessage): RedirectResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $message = $sendMessage->handle($conversation, $sender, $request->string('body')->value());

        $this->toast('Message sent.');

        // The conversation opens at its latest messages; the anchor brings
        // the new one into view.
        return redirect(route('conversations.show', $conversation).'#message-'.$message->id);
    }
}
