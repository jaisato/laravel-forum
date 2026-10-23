<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Models\User;

it('gives a reaction once, however many times it is sent', function () {
    $reply = forumReply(forumThread());
    $token = apiToken(User::factory()->create());

    foreach (range(1, 2) as $attempt) {
        $this->withToken($token)
            ->putJson("/api/v1/posts/{$reply->id}/reactions/like")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0', ['type' => 'like', 'emoji' => '👍', 'count' => 1, 'reacted' => true])
            ->assertJsonPath('data.1.count', 0);
    }

    expect($reply->reactions()->count())->toBe(1)
        ->and($reply->author->fresh()->reputation)->toBe(1);
});

it('takes a reaction back once, however many times it is sent', function () {
    $reply = forumReply(forumThread());
    $token = apiToken(User::factory()->create());

    $this->withToken($token)->putJson("/api/v1/posts/{$reply->id}/reactions/love")->assertOk();

    foreach (range(1, 2) as $attempt) {
        $this->withToken($token)
            ->deleteJson("/api/v1/posts/{$reply->id}/reactions/love")
            ->assertOk()
            ->assertJsonPath('data.1', ['type' => 'love', 'emoji' => '❤️', 'count' => 0, 'reacted' => false]);
    }

    expect($reply->reactions()->count())->toBe(0)
        ->and($reply->author->fresh()->reputation)->toBe(0);
});

it('needs a token with the write ability', function () {
    $reply = forumReply(forumThread());

    $this->putJson("/api/v1/posts/{$reply->id}/reactions/like")->assertUnauthorized();

    $this->withToken(apiToken(User::factory()->create(), ['read']))
        ->putJson("/api/v1/posts/{$reply->id}/reactions/like")
        ->assertForbidden();
});

it('does not let members react to their own posts', function () {
    $reply = forumReply(forumThread());

    $this->withToken(apiToken($reply->author))
        ->putJson("/api/v1/posts/{$reply->id}/reactions/like")
        ->assertForbidden();
});

it('answers 404 to members for posts they may not see', function () {
    $reply = forumReply(forumThread());
    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    $this->withToken(apiToken(User::factory()->create()))
        ->putJson("/api/v1/posts/{$reply->id}/reactions/like")
        ->assertNotFound();
});

it('knows only the forum reactions', function () {
    $reply = forumReply(forumThread());

    $this->withToken(apiToken(User::factory()->create()))
        ->putJson("/api/v1/posts/{$reply->id}/reactions/dislike")
        ->assertNotFound();
});
