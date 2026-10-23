<?php

declare(strict_types=1);

use App\Actions\Messaging\LeaveConversation;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Models\Conversation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A conversation the sender starts the way members do.
 */
function conversationFrom(User $sender, User|array $recipients, string $body = 'Hola, ¿tienes un **minuto**?'): Conversation
{
    return app(StartConversation::class)->handle($sender, collect(is_array($recipients) ? $recipients : [$recipients]), 'Una pregunta sobre colas', $body);
}

/**
 * @return array{id: int, name: string, username: string, avatar_url: null, role: string}
 */
function summaryOf(User $user): array
{
    return [
        'id' => $user->id,
        'name' => $user->name,
        'username' => $user->username,
        'avatar_url' => null,
        'role' => $user->role->value,
    ];
}

it('is for members only', function () {
    $conversation = conversationFrom(User::factory()->create(), User::factory()->create());

    $this->get(route('conversations.index'))->assertRedirect(route('login'));
    $this->get(route('conversations.create'))->assertRedirect(route('login'));
    $this->post(route('conversations.store'))->assertRedirect(route('login'));
    $this->get(route('conversations.show', $conversation))->assertRedirect(route('login'));
});

describe('the inbox', function () {
    it('lists the conversations of the member, latest first, with what is unread', function () {
        [$alice, $bob, $carol] = User::factory()->count(3)->create();
        $withBob = conversationFrom($alice, $bob);
        $this->travel(5)->minutes();
        $withCarol = conversationFrom($carol, $alice, 'Te escribo por lo de **Horizon**');
        $left = conversationFrom($bob, $alice);
        app(LeaveConversation::class)->handle($left, $alice);
        conversationFrom($bob, $carol);

        $this->actingAs($alice)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('conversations/Index')
                ->has('conversations.data', 2)
                ->where('conversations.data.0', [
                    'id' => $withCarol->id,
                    'subject' => 'Una pregunta sobre colas',
                    'last_message_at' => $withCarol->fresh()->last_message_at->toIso8601String(),
                    'is_unread' => true,
                    'participants' => [summaryOf($carol)],
                    'last_message' => [
                        'excerpt' => 'Te escribo por lo de Horizon',
                        'author' => summaryOf($carol),
                    ],
                ])
                ->where('conversations.data.1.id', $withBob->id)
                ->where('conversations.data.1.is_unread', false));
    });

    it('says when there is nothing to read', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('conversations.index'))
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
    });
});

describe('a conversation', function () {
    it('shows its messages to the participants and marks them read', function () {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);
        $reply = app(SendMessage::class)->handle($conversation, $alice, 'Es sobre `queue:work`.');
        $first = $conversation->messages()->oldest('id')->first();

        expect($bob->unreadConversationsCount())->toBe(1);

        $this->actingAs($bob)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('conversations/Show')
                ->where('conversation', [
                    'id' => $conversation->id,
                    'subject' => 'Una pregunta sobre colas',
                    'participants' => [summaryOf($alice), summaryOf($bob)],
                ])
                ->has('messages.data', 2)
                ->where('messages.data.0', [
                    'id' => $first->id,
                    'body_html' => '<p>Hola, ¿tienes un <strong>minuto</strong>?</p>',
                    'created_at' => $first->created_at->toIso8601String(),
                    'author' => summaryOf($alice),
                    'is_own' => false,
                ])
                ->where('messages.data.1.id', $reply->id)
                ->where('can', ['reply' => true, 'leave' => true]));

        expect($bob->unreadConversationsCount())->toBe(0);
    });

    it('opens on the latest messages', function () {
        config(['forum.pagination.messages' => 2]);
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);
        app(SendMessage::class)->handle($conversation, $bob, 'Dime.');
        $latest = app(SendMessage::class)->handle($conversation, $alice, 'Ya está resuelto.');

        $this->actingAs($bob)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.meta.current_page', 2)
                ->has('messages.data', 1)
                ->where('messages.data.0.id', $latest->id));

        $this->actingAs($bob)
            ->get(route('conversations.show', [$conversation, 'page' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.meta.current_page', 1)
                ->has('messages.data', 2));
    });

    it('marks read only as far as the page the member opened', function () {
        config(['forum.pagination.messages' => 2]);
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);
        app(SendMessage::class)->handle($conversation, $alice, '¿Sigues ahí?');
        app(SendMessage::class)->handle($conversation, $alice, 'Ya está resuelto.');

        $this->actingAs($bob)->get(route('conversations.show', [$conversation, 'page' => 1]))->assertOk();

        expect($bob->unreadConversationsCount())->toBe(1);

        $this->actingAs($bob)->get(route('conversations.show', $conversation))->assertOk();

        expect($bob->unreadConversationsCount())->toBe(0);
    });

    it('does not exist for anyone but its participants', function (Closure $stranger) {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);
        $user = $stranger($conversation, $bob);

        $this->actingAs($user)->get(route('conversations.show', $conversation))->assertNotFound();
        $this->actingAs($user)->post(route('conversations.messages.store', $conversation), ['body' => 'Hola'])->assertNotFound();
        $this->actingAs($user)->post(route('conversations.leave', $conversation))->assertNotFound();

        expect($conversation->messages()->count())->toBe(1);
    })->with([
        'another member' => [fn () => User::factory()->create()],
        'an administrator' => [fn () => User::factory()->admin()->create()],
        'a participant who left' => [function (Conversation $conversation, User $bob): User {
            app(LeaveConversation::class)->handle($conversation, $bob);

            return $bob;
        }],
    ]);

    it('lets banned participants read but not reply', function () {
        $alice = User::factory()->create();
        $banned = User::factory()->create();
        $conversation = conversationFrom($alice, $banned);
        $banned->forceFill(['banned_at' => now()])->save();

        $this->actingAs($banned)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page->where('can', ['reply' => false, 'leave' => true]));

        $this->actingAs($banned)
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Hola'])
            ->assertForbidden();
    });
});

describe('starting one', function () {
    it('fills in the member a profile sent them to write to', function () {
        $alice = User::factory()->create();
        $bob = User::factory()->create(['username' => 'bob']);

        $this->actingAs($alice)
            ->get(route('conversations.create', ['to' => 'bob']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('conversations/Create')
                ->where('recipients', [summaryOf($bob)]));
    });

    it('fills in nobody the member may not write to', function (Closure $to) {
        $alice = User::factory()->create(['username' => 'alice']);

        $this->actingAs($alice)
            ->get(route('conversations.create', ['to' => $to()]))
            ->assertInertia(fn (Assert $page) => $page->where('recipients', []));
    })->with([
        'nobody' => [fn () => 'nadie'],
        'themselves' => [fn () => 'alice'],
        'a member who takes no private messages' => [fn () => User::factory()->create(['preferences' => ['allow_private_messages' => false]])->username],
    ]);

    it('starts the conversation and takes the member to it', function () {
        $alice = User::factory()->create();
        $bob = User::factory()->create(['username' => 'bob']);
        $carol = User::factory()->create(['username' => 'carol']);

        $response = $this->actingAs($alice)->post(route('conversations.store'), [
            'recipients' => ['@Bob', ' carol '],
            'subject' => '  Una pregunta sobre colas  ',
            'body' => '¿Usáis **Horizon**?',
        ]);

        $conversation = Conversation::query()->sole();

        $response->assertRedirect(route('conversations.show', $conversation))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Message sent.']);

        expect($conversation->subject)->toBe('Una pregunta sobre colas')
            ->and($conversation->participants()->pluck('users.id')->sort()->values()->all())->toBe([$alice->id, $bob->id, $carol->id])
            ->and($conversation->messages()->sole()->body)->toBe('¿Usáis **Horizon**?');
    });

    it('validates the conversation', function (Closure $changes, string $field) {
        $alice = User::factory()->create(['username' => 'alice']);
        User::factory()->create(['username' => 'bob']);
        User::factory()->create(['username' => 'quiet', 'preferences' => ['allow_private_messages' => false]]);

        $this->actingAs($alice)
            ->post(route('conversations.store'), [
                'recipients' => ['bob'],
                'subject' => 'Una pregunta sobre colas',
                'body' => '¿Usáis Horizon?',
                ...$changes(),
            ])
            ->assertSessionHasErrors($field);

        expect(Conversation::query()->count())->toBe(0);
    })->with([
        'no recipients' => [fn () => ['recipients' => []], 'recipients'],
        'an unknown member' => [fn () => ['recipients' => ['bob', 'nadie']], 'recipients'],
        'themselves' => [fn () => ['recipients' => ['alice']], 'recipients'],
        'a member who takes no private messages' => [fn () => ['recipients' => ['quiet']], 'recipients'],
        'too many recipients' => [fn () => ['recipients' => User::factory()->count(11)->create()->pluck('username')->all()], 'recipients'],
        'no subject' => [fn () => ['subject' => ''], 'subject'],
        'a long subject' => [fn () => ['subject' => str_repeat('a', 151)], 'subject'],
        'no message' => [fn () => ['body' => ''], 'body'],
        'a long message' => [fn () => ['body' => str_repeat('a', 10_001)], 'body'],
    ]);

    it('refuses members who may not take part', function (Closure $member) {
        $user = $member();
        User::factory()->create(['username' => 'bob']);

        $this->actingAs($user)->get(route('conversations.create'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('conversations.store'), ['recipients' => ['bob'], 'subject' => 'Hola', 'body' => 'Hola'])
            ->assertForbidden();
    })->with([
        'unverified' => [fn () => User::factory()->unverified()->create()],
        'banned' => [fn () => User::factory()->banned()->create()],
    ]);

    it('slows down members who write too fast', function () {
        $alice = User::factory()->create();
        User::factory()->create(['username' => 'bob']);
        $attempts = config('forum.rate_limits.messages.attempts');

        foreach (range(1, $attempts) as $i) {
            $this->actingAs($alice)->post(route('conversations.store'), ['recipients' => ['bob'], 'subject' => "Pregunta {$i}", 'body' => 'Hola']);
        }

        $this->actingAs($alice)
            ->from(route('conversations.create'))
            ->post(route('conversations.store'), ['recipients' => ['bob'], 'subject' => 'Una de más', 'body' => 'Hola'])
            ->assertRedirect(route('conversations.create'))
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Too many attempts. Please wait a moment.']);

        expect(Conversation::query()->count())->toBe($attempts);
    });
});

describe('replying', function () {
    it('adds the reply and moves the read markers', function () {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);

        $response = $this->actingAs($bob)->post(route('conversations.messages.store', $conversation), [
            'body' => 'Claro, **dime**.',
        ]);

        $reply = $conversation->messages()->latest('id')->first();

        $response->assertRedirect(route('conversations.show', $conversation).'#message-'.$reply->id)
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Message sent.']);

        expect($reply->body)->toBe('Claro, **dime**.')
            ->and($reply->author->is($bob))->toBeTrue()
            ->and($bob->unreadConversationsCount())->toBe(0)
            ->and($alice->unreadConversationsCount())->toBe(1);
    });

    it('validates the reply', function (string $body) {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);

        $this->actingAs($bob)
            ->post(route('conversations.messages.store', $conversation), ['body' => $body])
            ->assertSessionHasErrors('body');

        expect($conversation->messages()->count())->toBe(1);
    })->with([
        'empty' => [''],
        'too long' => [str_repeat('a', 10_001)],
    ]);
});

describe('leaving', function () {
    it('takes the conversation out of the member’s inbox, not the others’', function () {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = conversationFrom($alice, $bob);

        $this->actingAs($bob)
            ->post(route('conversations.leave', $conversation))
            ->assertRedirect(route('conversations.index'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You left the conversation.']);

        $this->actingAs($bob)->get(route('conversations.show', $conversation))->assertNotFound();
        $this->actingAs($bob)->get(route('conversations.index'))
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
        $this->actingAs($alice)->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.participants', [summaryOf($alice)]));
    });
});
