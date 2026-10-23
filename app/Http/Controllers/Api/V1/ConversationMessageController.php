<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The messages of a conversation, oldest first, and the replies to it.
 */
final class ConversationMessageController extends Controller
{
    /**
     * Reading them marks the conversation read, as opening it on the web.
     */
    public function index(Request $request, Conversation $conversation, MarkConversationRead $markRead): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        /** @var User $member */
        $member = $request->user();

        $messages = $conversation->messages()
            ->with('author')
            ->orderBy('id')
            ->paginate((int) config('forum.pagination.messages'));

        $markRead->handle($conversation, $member);

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation, SendMessage $sendMessage): JsonResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $message = $sendMessage->handle($conversation, $sender, $request->string('body')->value());

        return (new MessageResource($message->load('author')))->response()->setStatusCode(201);
    }
}
