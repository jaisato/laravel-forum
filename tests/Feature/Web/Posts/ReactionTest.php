<?php

declare(strict_types=1);

use App\Models\Reaction;
use App\Models\User;

it('toggles a reaction', function () {
    $reply = forumReply(forumThread());
    $member = User::factory()->create();

    $this->actingAs($member)
        ->from(route('threads.show', $reply->thread))
        ->post(route('posts.reactions.toggle', $reply), ['type' => 'love'])
        ->assertRedirect(route('threads.show', $reply->thread));

    expect(Reaction::query()->where('post_id', $reply->id)->where('user_id', $member->id)->value('type')?->value)->toBe('love')
        ->and($reply->author->fresh()->reputation)->toBe(1);

    $this->actingAs($member)->post(route('posts.reactions.toggle', $reply), ['type' => 'love']);

    expect(Reaction::query()->where('post_id', $reply->id)->count())->toBe(0)
        ->and($reply->author->fresh()->reputation)->toBe(0);
});

it('refuses reactions to one\'s own posts', function () {
    $reply = forumReply(forumThread());

    $this->actingAs($reply->author)
        ->post(route('posts.reactions.toggle', $reply), ['type' => 'like'])
        ->assertForbidden();
});

it('validates the reaction type', function () {
    $reply = forumReply(forumThread());

    $this->actingAs(User::factory()->create())
        ->post(route('posts.reactions.toggle', $reply), ['type' => 'angry'])
        ->assertSessionHasErrors('type');
});

it('sends guests to the login page', function () {
    $this->post(route('posts.reactions.toggle', forumReply(forumThread())), ['type' => 'like'])
        ->assertRedirect(route('login'));
});
