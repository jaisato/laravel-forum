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

it('turns members away before looking the post up, whether it exists or not', function (string $route) {
    $member = User::factory()->create();
    $hidden = forumReply(forumThread());
    app(HidePost::class)->handle($hidden, User::factory()->moderator()->create());
    $deleted = forumReply(forumThread());
    $deleted->delete();

    // Hidden, deleted or never there: the same answer, so a member cannot
    // tell them apart (the API turns tokens away the same way).
    foreach ([$hidden->id, $deleted->id, $deleted->id + 1000] as $id) {
        $this->actingAs($member)->post("/moderation/posts/{$id}/{$route}")->assertForbidden();
    }
})->with(['hide', 'approve', 'restore']);

it('sends guests to the login page', function () {
    $this->post(route('moderation.posts.hide', forumReply(forumThread())))->assertRedirect(route('login'));
});
