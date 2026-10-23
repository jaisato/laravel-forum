<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messaging\LeaveConversation;
use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\StartConversation;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\MemberConversations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Private messages (spec §4.3): the inbox, a conversation, starting one and
 * leaving it. Only active participants see a conversation; everyone else,
 * staff included, gets a 404 (ConversationPolicy).
 */
final class ConversationController extends Controller
{
    use FlashesToasts;

    /**
     * The member's conversations, the latest activity first; those they left
     * are gone from it.
     */
    public function index(Request $request, MemberConversations $inbox): Response
    {
        /** @var User $member */
        $member = $request->user();

        return Inertia::render('conversations/Index', [
            'conversations' => ConversationResource::collection($inbox->paginate($member)),
        ]);
    }

    /**
     * The new conversation form. ?to={username} (a profile's "Send a
     * message") fills in that member, when the sender may write to them.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Conversation::class);

        $to = $request->query('to');
        $recipient = is_string($to) && $to !== ''
            ? User::query()->where('username', Str::lower(ltrim($to, '@')))->first()
            : null;

        return Inertia::render('conversations/Create', [
            'recipients' => $recipient !== null && Gate::allows('message', [Conversation::class, $recipient])
                ? [(new UserResource($recipient))->resolve($request)]
                : [],
        ]);
    }

    public function store(StoreConversationRequest $request, StartConversation $startConversation): RedirectResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $conversation = $startConversation->handle(
            $sender,
            $request->recipients(),
            $request->string('subject')->value(),
            $request->string('body')->value(),
        );

        $this->toast('Message sent.');

        return to_route('conversations.show', $conversation);
    }

    /**
     * A conversation, open at its latest messages unless another page is
     * asked for. Seeing it marks it read.
     */
    public function show(Request $request, Conversation $conversation, MarkConversationRead $markRead): Response
    {
        Gate::authorize('view', $conversation);

        /** @var User $member */
        $member = $request->user();
        $perPage = (int) config('forum.pagination.messages');
        $page = $request->integer('page') ?: max(1, (int) ceil($conversation->messages()->count() / $perPage));

        $messages = $conversation->messages()
            ->with('author')
            ->orderBy('id')
            ->paginate($perPage, page: $page);

        $markRead->handle($conversation, $member);

        $conversation->load(['participants' => MemberConversations::activeParticipants(...)]);

        return Inertia::render('conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'participants' => UserResource::collection($conversation->participants)->resolve($request),
            ],
            'messages' => MessageResource::collection($messages),
            'can' => [
                'reply' => Gate::allows('reply', $conversation),
                'leave' => Gate::allows('leave', $conversation),
            ],
        ]);
    }

    public function leave(Request $request, Conversation $conversation, LeaveConversation $leaveConversation): RedirectResponse
    {
        Gate::authorize('leave', $conversation);

        /** @var User $member */
        $member = $request->user();

        $leaveConversation->handle($conversation, $member);

        $this->toast('You left the conversation.');

        return to_route('conversations.index');
    }
}
