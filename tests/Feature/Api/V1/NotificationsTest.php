<?php

declare(strict_types=1);

use App\Actions\Messaging\StartConversation;
use App\Actions\Moderation\HidePost;
use App\Models\User;
use Illuminate\Support\Str;

it('needs a token with the read ability', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();

    $this->withToken(apiToken(User::factory()->create(), ['write']))
        ->getJson('/api/v1/notifications')
        ->assertForbidden();
});

it('lists the notifications of the member, newest first', function () {
    $member = User::factory()->create();
    $thread = forumThread($member);
    $this->travel(-5)->minutes();
    $first = forumReply($thread, body: 'Primera respuesta');
    $this->travelBack();
    $latest = forumReply($thread, body: 'Otra respuesta');
    forumReply(forumThread());

    $this->withToken(apiToken($member, ['read']))
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.type', 'thread_replied')
        ->assertJsonPath('data.0.excerpt', 'Otra respuesta')
        ->assertJsonPath('data.0.post_id', $latest->id)
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('data.1.post_id', $first->id);
});

it('lists only the unread ones when asked', function () {
    $member = User::factory()->create();
    $thread = forumThread($member);
    forumReply($thread);
    forumReply($thread);
    $member->notifications()->first()->markAsRead();

    $this->withToken(apiToken($member, ['read']))
        ->getJson('/api/v1/notifications?unread=1')
        ->assertJsonCount(1, 'data');
});

it('withholds what the member may no longer see', function () {
    $member = User::factory()->create();
    $reply = forumReply(forumThread($member), body: 'Algo que se ocultará');
    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    $this->withToken(apiToken($member, ['read']))
        ->getJson('/api/v1/notifications')
        ->assertJsonPath('data.0.title', 'This content is no longer available.')
        ->assertJsonPath('data.0.excerpt', null)
        ->assertJsonPath('data.0.post_id', null)
        ->assertJsonPath('data.0.url', null);
});

it('marks one as read, however many times it is sent', function () {
    $member = User::factory()->create();
    forumReply(forumThread($member));
    $notification = $member->notifications()->firstOrFail();
    $token = apiToken($member, ['read']);

    foreach (range(1, 2) as $attempt) {
        $this->withToken($token)
            ->putJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read_at', fn (?string $readAt): bool => $readAt !== null);
    }

    expect($member->unreadNotifications()->count())->toBe(0);
});

it('marks them all as read', function () {
    $member = User::factory()->create();
    $thread = forumThread($member);
    forumReply($thread);
    forumReply($thread);

    $this->withToken(apiToken($member, ['read']))
        ->putJson('/api/v1/notifications/read')
        ->assertNoContent();

    expect($member->unreadNotifications()->count())->toBe(0);
});

it('finds only the member\'s own notifications', function () {
    $owner = User::factory()->create();
    forumReply(forumThread($owner));
    $notification = $owner->notifications()->firstOrFail();
    $token = apiToken(User::factory()->create(), ['read']);

    $this->withToken($token)->putJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
    $this->withToken($token)->putJson('/api/v1/notifications/'.Str::uuid().'/read')->assertNotFound();
    $this->withToken($token)->putJson('/api/v1/notifications/123/read')->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('keeps private messages out of reach of a token without the messages ability', function () {
    $member = User::factory()->create();
    app(StartConversation::class)->handle(User::factory()->create(), collect([$member]), 'Asunto privado', 'Un secreto.');
    forumReply(forumThread($member), body: 'Una respuesta pública');
    $private = $member->notifications()->where('type', 'new_private_message')->sole();
    $readOnly = apiToken($member, ['read']);

    $this->withToken($readOnly)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.type', 'thread_replied')
        ->assertDontSee('Asunto privado')
        ->assertDontSee('Un secreto');

    $this->withToken($readOnly)->putJson("/api/v1/notifications/{$private->id}/read")->assertNotFound();
    $this->withToken($readOnly)->putJson('/api/v1/notifications/read')->assertNoContent();

    expect($private->fresh()->read_at)->toBeNull();

    $this->withToken(apiToken($member, ['read', 'messages']))
        ->getJson('/api/v1/notifications')
        ->assertJsonCount(2, 'data');
});
