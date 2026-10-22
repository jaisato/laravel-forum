<?php

declare(strict_types=1);

use App\Actions\Messaging\LeaveConversation;
use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

function startConversation(User $sender, User ...$recipients): Conversation
{
    return app(StartConversation::class)->handle($sender, collect($recipients), 'Una pregunta', 'Hola, ¿tienes un **minuto**?');
}

it('starts a conversation with its first message', function () {
    [$alice, $bob, $carol] = User::factory()->count(3)->create();

    $conversation = startConversation($alice, $bob, $carol);
    $message = $conversation->messages()->sole();

    expect($conversation->subject)->toBe('Una pregunta')
        ->and($conversation->participants()->pluck('users.id')->sort()->values()->all())->toBe([$alice->id, $bob->id, $carol->id])
        ->and($message->author->is($alice))->toBeTrue()
        ->and($message->body_html)->toBe('<p>Hola, ¿tienes un <strong>minuto</strong>?</p>')
        ->and($conversation->fresh()->last_message_id)->toBe($message->id)
        ->and($conversation->fresh()->last_message_at->equalTo($message->created_at))->toBeTrue();
});

it('refuses to let members write to themselves', function () {
    $alice = User::factory()->create();

    expect(fn () => startConversation($alice, $alice))->toThrow(ValidationException::class)
        ->and(Conversation::query()->count())->toBe(0);
});

it('refuses members who turned private messages off, except for staff', function () {
    $quiet = User::factory()->create(['preferences' => ['allow_private_messages' => false]]);

    expect(fn () => startConversation(User::factory()->create(), $quiet))->toThrow(ValidationException::class)
        ->and(Conversation::query()->count())->toBe(0);

    startConversation(User::factory()->moderator()->create(), $quiet);

    expect(Conversation::query()->count())->toBe(1);
});

it('refuses conversations without anyone else or with too many recipients', function () {
    $alice = User::factory()->create();
    $crowd = User::factory()->count(config('forum.messages.max_recipients') + 1)->create();

    expect(fn () => app(StartConversation::class)->handle($alice, collect(), 'Hola', 'Hola'))->toThrow(ValidationException::class)
        ->and(fn () => app(StartConversation::class)->handle($alice, $crowd, 'Hola', 'Hola'))->toThrow(ValidationException::class);
});

it('appends replies and keeps the conversation up to date', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    $conversation = startConversation($alice, $bob);
    $this->travel(10)->minutes();

    $reply = app(SendMessage::class)->handle($conversation, $bob, 'Claro, dime');

    expect($conversation->fresh()->last_message_id)->toBe($reply->id)
        ->and($conversation->fresh()->last_message_at->equalTo($reply->created_at))->toBeTrue()
        ->and($conversation->messages()->count())->toBe(2);
});

it('only lets active participants write', function () {
    [$alice, $bob, $outsider] = User::factory()->count(3)->create();
    $conversation = startConversation($alice, $bob);
    app(LeaveConversation::class)->handle($conversation, $bob);

    expect(fn () => app(SendMessage::class)->handle($conversation, $outsider, 'Hola'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SendMessage::class)->handle($conversation, $bob, 'Hola'))->toThrow(InvalidArgumentException::class);
});

it('hides a conversation from the member who left it', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    $conversation = startConversation($alice, $bob);

    app(LeaveConversation::class)->handle($conversation, $bob);

    expect($bob->conversations()->wherePivotNull('left_at')->count())->toBe(0)
        ->and($alice->conversations()->wherePivotNull('left_at')->count())->toBe(1)
        ->and($bob->unreadConversationsCount())->toBe(0);
});

it('counts the conversations with unread messages', function () {
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    $conversation = startConversation($alice, $bob, $carol);
    startConversation($carol, $bob);

    expect($alice->unreadConversationsCount())->toBe(0)
        ->and($bob->unreadConversationsCount())->toBe(2)
        ->and($carol->unreadConversationsCount())->toBe(1);

    app(MarkConversationRead::class)->handle($conversation, $bob);

    expect($bob->unreadConversationsCount())->toBe(1);

    app(SendMessage::class)->handle($conversation, $bob, 'Ya lo he leído');

    expect($bob->unreadConversationsCount())->toBe(1)
        ->and($alice->unreadConversationsCount())->toBe(1)
        ->and($carol->unreadConversationsCount())->toBe(1);
});

it('announces every message once committed', function () {
    Event::fake([MessageSent::class]);
    [$alice, $bob] = User::factory()->count(2)->create();

    $conversation = startConversation($alice, $bob);
    $reply = app(SendMessage::class)->handle($conversation, $bob, 'Claro');

    Event::assertDispatchedTimes(MessageSent::class, 2);
    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->is($reply));
});
