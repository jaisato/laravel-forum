<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Enums\PostStatus;
use App\Models\User;

it('hides a post with a reason', function () {
    $reply = forumReply(forumThread());

    $this->actingAs(User::factory()->moderator()->create())
        ->from(route('threads.show', $reply->thread))
        ->post(route('moderation.posts.hide', $reply), ['reason' => 'Spam.'])
        ->assertRedirect(route('threads.show', $reply->thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Post hidden.']);

    expect($reply->fresh()->status)->toBe(PostStatus::Hidden);
});

it('approves a post held for review', function () {
    $reply = forumReply(forumThread());
    app(HidePost::class)->handle($reply, null);

    $this->actingAs(User::factory()->moderator()->create())
        ->post(route('moderation.posts.approve', $reply))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Post approved.']);

    expect($reply->fresh()->status)->toBe(PostStatus::Published);
});

it('validates the reason', function () {
    $this->actingAs(User::factory()->moderator()->create())
        ->post(route('moderation.posts.hide', forumReply(forumThread())), ['reason' => str_repeat('a', 501)])
        ->assertSessionHasErrors('reason');
});

it('is for staff only', function (string $route) {
    $reply = forumReply(forumThread());

    $this->actingAs(User::factory()->create())->post(route("moderation.posts.{$route}", $reply))->assertForbidden();
    $this->actingAs(forumUser('banned moderator'))->post(route("moderation.posts.{$route}", $reply))->assertForbidden();
})->with(['hide', 'approve']);

it('sends guests to the login page', function () {
    $this->post(route('moderation.posts.hide', forumReply(forumThread())))->assertRedirect(route('login'));
});
