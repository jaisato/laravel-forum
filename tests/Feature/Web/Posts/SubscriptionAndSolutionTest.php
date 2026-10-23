<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Enums\PostStatus;
use App\Http\Requests\MarkSolutionRequest;
use App\Models\User;
use App\Services\ThreadSubscriptions;

it('follows and unfollows a thread', function () {
    $thread = forumThread();
    $member = User::factory()->create();
    $subscriptions = app(ThreadSubscriptions::class);

    $this->actingAs($member)
        ->from(route('threads.show', $thread))
        ->post(route('threads.subscription.store', $thread))
        ->assertRedirect(route('threads.show', $thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You are following this thread.']);

    expect($subscriptions->isSubscribed($thread, $member))->toBeTrue();

    $this->actingAs($member)
        ->delete(route('threads.subscription.destroy', $thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'You no longer follow this thread.']);

    expect($subscriptions->isSubscribed($thread, $member))->toBeFalse();
});

it('refuses subscriptions from guests and members who may not take part', function () {
    $thread = forumThread();

    $this->post(route('threads.subscription.store', $thread))->assertRedirect(route('login'));
    $this->actingAs(forumUser('banned'))->post(route('threads.subscription.store', $thread))->assertForbidden();
});

it('lets the thread author mark and unmark the solution', function () {
    $thread = forumThread();
    $answer = forumReply($thread);

    $this->actingAs($thread->author)
        ->from(route('threads.show', $thread))
        ->post(route('threads.solution.store', $thread), ['post_id' => $answer->id])
        ->assertRedirect(route('threads.show', $thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Marked as the solution.']);

    expect($thread->fresh()->solution_post_id)->toBe($answer->id);

    $this->actingAs($thread->author)
        ->delete(route('threads.solution.destroy', $thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Solution unmarked.']);

    expect($thread->fresh()->solution_post_id)->toBeNull();
});

it('refuses solutions chosen by others', function () {
    $thread = forumThread();
    $answer = forumReply($thread);

    $this->actingAs(User::factory()->create())
        ->post(route('threads.solution.store', $thread), ['post_id' => $answer->id])
        ->assertForbidden();
});

it('validates the chosen solution', function (string $case) {
    $thread = forumThread();
    $post = match ($case) {
        'opening post' => $thread->firstPost,
        'reply of another thread' => forumReply(forumThread()),
        'hidden reply' => tap(forumReply($thread), fn ($reply) => app(HidePost::class)->handle($reply, User::factory()->moderator()->create())),
    };

    $this->actingAs($thread->author)
        ->post(route('threads.solution.store', $thread), ['post_id' => $post->id])
        ->assertSessionHasErrors('post_id');

    expect($thread->fresh()->solution_post_id)->toBeNull();
})->with(['opening post', 'reply of another thread', 'hidden reply']);

it('refuses a reply hidden while the request was being checked', function () {
    $thread = forumThread();
    $answer = forumReply($thread);

    // Runs once the request has passed validation, before the solution is marked.
    app()->afterResolving(MarkSolutionRequest::class, fn () => $answer->forceFill(['status' => PostStatus::Hidden])->saveQuietly());

    $this->actingAs($thread->author)
        ->post(route('threads.solution.store', $thread), ['post_id' => $answer->id])
        ->assertSessionHasErrors(['post_id' => 'This reply cannot be the solution.']);

    expect($thread->fresh()->solution_post_id)->toBeNull();
});
