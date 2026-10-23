<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Models\User;

it('lets authors edit their reply', function () {
    $reply = forumReply(forumThread());

    $this->actingAs($reply->author)
        ->from(route('threads.show', $reply->thread))
        ->patch(route('posts.update', $reply), ['body' => 'Texto **corregido**.'])
        ->assertRedirect(route('threads.show', $reply->thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Post updated.']);

    expect($reply->fresh())
        ->body->toBe('Texto **corregido**.')
        ->edited_at->not->toBeNull();
});

it('validates an edit', function () {
    $reply = forumReply(forumThread());

    $this->actingAs($reply->author)
        ->patch(route('posts.update', $reply), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('refuses to let members edit or delete the posts of others', function () {
    $reply = forumReply(forumThread());
    $other = User::factory()->create();

    $this->actingAs($other)->patch(route('posts.update', $reply), ['body' => 'Mío ahora.'])->assertForbidden();
    $this->actingAs($other)->delete(route('posts.destroy', $reply))->assertForbidden();
});

it('sends guests to the login page', function () {
    $reply = forumReply(forumThread());

    $this->patch(route('posts.update', $reply), ['body' => 'Texto.'])->assertRedirect(route('login'));
    $this->delete(route('posts.destroy', $reply))->assertRedirect(route('login'));
});

it('lets authors delete their reply', function () {
    $reply = forumReply(forumThread());

    $this->actingAs($reply->author)
        ->from(route('threads.show', $reply->thread))
        ->delete(route('posts.destroy', $reply))
        ->assertRedirect(route('threads.show', $reply->thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Post deleted.']);

    expect($reply->fresh()->trashed())->toBeTrue();
});

it('deletes the whole thread with its opening post and goes back to the category', function () {
    $thread = forumThread();

    $this->actingAs($thread->author)
        ->delete(route('posts.destroy', $thread->firstPost))
        ->assertRedirect(route('categories.show', $thread->category))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thread deleted.']);

    expect($thread->fresh()->trashed())->toBeTrue();
});

it('lets staff restore a deleted post', function () {
    $reply = forumReply(forumThread());
    app(DeletePost::class)->handle($reply, $reply->author);

    $this->actingAs($reply->author)->post(route('moderation.posts.restore', $reply))->assertForbidden();

    $this->actingAs(User::factory()->moderator()->create())
        ->from(route('threads.show', $reply->thread))
        ->post(route('moderation.posts.restore', $reply))
        ->assertRedirect(route('threads.show', $reply->thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Post restored.']);

    expect($reply->fresh()->trashed())->toBeFalse();
});

it('slows down members who edit too often', function () {
    $reply = forumReply(forumThread());

    foreach (range(1, 10) as $edit) {
        $this->actingAs($reply->author)
            ->patch(route('posts.update', $reply), ['body' => "Versión número {$edit}."])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($reply->author)
        ->from(route('threads.show', $reply->thread))
        ->patch(route('posts.update', $reply), ['body' => 'Una de más.'])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Too many attempts. Please wait a moment.']);

    expect($reply->fresh()->body)->toBe('Versión número 10.');
});
