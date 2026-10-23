<?php

declare(strict_types=1);

use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Models\Conversation;
use App\Models\User;

/**
 * A conversation the sender started with the recipient, through the action
 * the web uses.
 */
function apiConversation(User $sender, User $recipient, string $body = 'Hola, ¿qué tal?'): Conversation
{
    return app(StartConversation::class)->handle($sender, collect([$recipient]), 'Una pregunta', $body);
}

it('needs a token with the messages ability', function () {
    $member = User::factory()->create();

    $this->getJson('/api/v1/conversations')->assertUnauthorized();

    $this->withToken(apiToken($member, ['read', 'write']))
        ->getJson('/api/v1/conversations')
        ->assertForbidden();

    $this->withToken(apiToken($member, ['read', 'write']))
        ->postJson('/api/v1/conversations', ['recipients' => ['ana'], 'subject' => 'Hola', 'body' => 'Hola, Ana.'])
        ->assertForbidden();
});

it('lists the conversations of the member, unread ones marked', function () {
    $member = User::factory()->create();
    $ana = User::factory()->create(['username' => 'ana']);
    $conversation = apiConversation($ana, $member);
    apiConversation($ana, User::factory()->create());

    $this->withToken(apiToken($member, ['messages']))
        ->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.subject', 'Una pregunta')
        ->assertJsonPath('data.0.is_unread', true)
        ->assertJsonPath('data.0.participants.0.username', 'ana')
        ->assertJsonMissingPath('data.0.participants.0.email');
});

it('starts a conversation', function () {
    $member = User::factory()->create();
    User::factory()->create(['username' => 'ana']);

    $this->withToken(apiToken($member, ['messages']))
        ->postJson('/api/v1/conversations', ['recipients' => ['@Ana'], 'subject' => 'Una pregunta', 'body' => 'Hola, Ana.'])
        ->assertCreated()
        ->assertJsonPath('data.subject', 'Una pregunta')
        ->assertJsonPath('data.participants.0.username', 'ana')
        ->assertJsonPath('data.is_unread', false);

    expect(Conversation::query()->count())->toBe(1);
});

it('validates the conversation', function () {
    $this->withToken(apiToken(User::factory()->create(), ['messages']))
        ->postJson('/api/v1/conversations', ['recipients' => ['nadie'], 'body' => 'Hola.'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recipients', 'subject']);
});

it('shows a conversation and reads its messages, which marks it read', function () {
    $member = User::factory()->create();
    $conversation = apiConversation(User::factory()->create(), $member, 'Hola, **¿qué tal?**');
    $token = apiToken($member, ['messages']);

    $this->withToken($token)
        ->getJson("/api/v1/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $conversation->id)
        ->assertJsonPath('data.is_unread', true);

    $this->withToken($token)
        ->getJson("/api/v1/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body_html', '<p>Hola, <strong>¿qué tal?</strong></p>')
        ->assertJsonPath('data.0.is_own', false);

    $this->withToken($token)
        ->getJson("/api/v1/conversations/{$conversation->id}")
        ->assertJsonPath('data.is_unread', false);
});

it('marks read only as far as the page the member read', function () {
    config(['forum.pagination.messages' => 2]);
    $sender = User::factory()->create();
    $member = User::factory()->create();
    $conversation = apiConversation($sender, $member, 'Mensaje 1');
    app(SendMessage::class)->handle($conversation, $sender, 'Mensaje 2');
    app(SendMessage::class)->handle($conversation, $sender, 'Mensaje 3');
    $token = apiToken($member, ['messages']);

    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertJsonCount(2, 'data');

    // The third message is on page 2, still unread.
    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}")->assertJsonPath('data.is_unread', true);

    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}/messages?page=2")->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}")->assertJsonPath('data.is_unread', false);

    // Reading page 1 again does not move the mark back.
    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}/messages");
    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}")->assertJsonPath('data.is_unread', false);
});

it('replies', function () {
    $member = User::factory()->create();
    $conversation = apiConversation(User::factory()->create(), $member);

    $this->withToken(apiToken($member, ['messages']))
        ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Muy bien, gracias.'])
        ->assertCreated()
        ->assertJsonPath('data.is_own', true)
        ->assertJsonPath('data.body_html', '<p>Muy bien, gracias.</p>');

    expect($conversation->messages()->count())->toBe(2);
});

it('leaves a conversation, which then is not found', function () {
    $member = User::factory()->create();
    $conversation = apiConversation(User::factory()->create(), $member);
    $token = apiToken($member, ['messages']);

    $this->withToken($token)
        ->postJson("/api/v1/conversations/{$conversation->id}/leave")
        ->assertNoContent();

    $this->withToken($token)
        ->getJson("/api/v1/conversations/{$conversation->id}")
        ->assertNotFound();
});

it('answers 404 to anyone outside the conversation, staff included', function (string $role) {
    $conversation = apiConversation(User::factory()->create(), User::factory()->create());
    $token = apiToken(forumUser($role), ['read', 'write', 'messages']);

    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}")->assertNotFound();
    $this->withToken($token)->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertNotFound();
    $this->withToken($token)
        ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Me cuelo.'])
        ->assertNotFound();
    $this->withToken($token)->postJson("/api/v1/conversations/{$conversation->id}/leave")->assertNotFound();
})->with(['member', 'moderator', 'admin']);
