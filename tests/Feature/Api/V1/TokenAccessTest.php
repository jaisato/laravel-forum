<?php

declare(strict_types=1);

use App\Actions\Forum\ReactToPost;
use App\Enums\ReactionType;
use App\Models\User;

/*
 * Public reads take an optional token: with one, the client reads as its
 * member, who sees what they see on the web.
 */

it('reads as the member the token belongs to', function () {
    $member = User::factory()->create();
    $reply = forumReply(forumThread());
    app(ReactToPost::class)->add($reply, $member, ReactionType::Like);

    $this->getJson("/api/v1/threads/{$reply->thread_id}/posts")
        ->assertJsonPath('data.1.reactions.0.reacted', false);

    $this->withToken(apiToken($member))
        ->getJson("/api/v1/threads/{$reply->thread_id}/posts")
        ->assertJsonPath('data.1.reactions.0.reacted', true)
        ->assertJsonPath('data.1.can.report', true);
});

it('refuses a token it does not know, even where guests may read', function () {
    $this->withToken('1|not-a-token')->getJson('/api/v1/categories')->assertUnauthorized();

    $member = User::factory()->create();
    $plain = apiToken($member);
    $member->tokens()->delete();

    $this->withToken($plain)->getJson('/api/v1/categories')->assertUnauthorized();
});

it('needs the read ability to read as the member', function () {
    $this->withToken(apiToken(User::factory()->create(), ['write']))
        ->getJson('/api/v1/categories')
        ->assertForbidden();
});
