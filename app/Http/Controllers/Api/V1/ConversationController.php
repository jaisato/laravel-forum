<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\LeaveConversation;
use App\Actions\Messaging\StartConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\MemberConversations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Private conversations (the messages ability): the inbox, starting one,
 * one of them and leaving it. Only their participants find them; staff
 * included, everyone else gets 404.
 */
final class ConversationController extends Controller
{
    public function index(Request $request, MemberConversations $inbox): AnonymousResourceCollection
    {
        return ConversationResource::collection($inbox->paginate($this->member($request)));
    }

    /**
     * Recipients go by username ("@" optional, any case).
     */
    public function store(StoreConversationRequest $request, StartConversation $startConversation, MemberConversations $inbox): JsonResponse
    {
        $sender = $this->member($request);

        $conversation = $startConversation->handle(
            $sender,
            $request->recipients(),
            $request->string('subject')->value(),
            $request->string('body')->value(),
        );

        return (new ConversationResource($inbox->one($sender, $conversation)))->response()->setStatusCode(201);
    }

    /**
     * The conversation as the inbox lists it; its messages are read from
     * /conversations/{conversation}/messages.
     */
    public function show(Request $request, Conversation $conversation, MemberConversations $inbox): ConversationResource
    {
        Gate::authorize('view', $conversation);

        return new ConversationResource($inbox->one($this->member($request), $conversation));
    }

    /**
     * The conversation goes on for the others; the member no longer finds it.
     */
    public function leave(Request $request, Conversation $conversation, LeaveConversation $leaveConversation): Response
    {
        Gate::authorize('leave', $conversation);

        $leaveConversation->handle($conversation, $this->member($request));

        return response()->noContent();
    }

    private function member(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
